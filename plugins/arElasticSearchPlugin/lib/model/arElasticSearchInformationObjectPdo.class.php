<?php

/*
 * This file is part of the Access to Memory (AtoM) software.
 *
 * Access to Memory (AtoM) is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Access to Memory (AtoM) is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Access to Memory (AtoM).  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Manage information objects in search index
 *
 * @package    AccesstoMemory
 * @subpackage arElasticSearchPlugin
 * @author     David Juhasz <david@artefactual.com>
 */
class arElasticSearchInformationObjectPdo
{
  public
    $ancestors,
    $doc,
    $repository,
    $sourceCulture,
    $creators = array(),
    $inheritedCreators = array();

  protected
    $data = array(),
    $events,
    $termParentList = array(),
    $languages = array(),
    $scripts = array();

  protected static
    $conn,
    $lookups,
    $statements;

  public function __construct($id, $options = array())
  {
    if (isset($options['conn']))
    {
      self::$conn = $options['conn'];
    }

    if (!isset(self::$conn))
    {
      self::$conn = Propel::getConnection();
    }

    $this->loadData($id, $options);

    // Get inherited ancestors
    if (isset($options['ancestors']))
    {
      $this->ancestors = $options['ancestors'];
    }

    // Get system term id hierarchy
    if (isset($options['terms']))
    {
      $this->termParentList = $options['terms'];
    }

    // Get inherited repository, unless a repository is set at current level
    if (isset($options['repository']) && !$this->__isset('repository_id'))
    {
      $this->repository = $options['repository'];
    }

    // Get inherited creators
    if (isset($options['inheritedCreators']))
    {
      $this->inheritedCreators = $options['inheritedCreators'];
    }

    // Get creators
    $this->creators = $this->getActors(array('typeId' => QubitTerm::CREATION_ID));

    // Ignore inherited creators if there are directly related creators
    if (count($this->creators) > 0)
    {
      $this->inheritedCreators = array();
    }
    // Otherwise, get them from the options
    elseif (isset($options['inheritedCreators']))
    {
      $this->inheritedCreators = $options['inheritedCreators'];
    }
    // Or from the closest ancestor
    else
    {
      $this->inheritedCreators = $this->getClosestCreators();
    }
  }

  public function __isset($name)
  {
    if ('events' == $name)
    {
      return isset($this->events);
    }

    return isset($this->data[$name]);
  }

  public function __get($name)
  {
    if ('events' == $name)
    {
      return $this->events;
    }

    if (isset($this->data[$name]))
    {
      return $this->data[$name];
    }
  }

  public function __set($name, $value)
  {
    if ('events' == $name)
    {
      $this->events = $value;

      return;
    }

    $this->data[$name] = $value;
  }

  protected function loadData($id, $options = array())
  {
    if (!isset(self::$statements['informationObject']))
    {
      $sql = 'SELECT
         io.*,
         obj.created_at,
         obj.updated_at,
         slug.slug,
         pubstat.status_id as publication_status_id,
         do.id as digital_object_id,
         do.media_type_id as media_type_id,
         do.usage_id as usage_id,
         do.name as filename
       FROM '.QubitInformationObject::TABLE_NAME.' io
       JOIN '.QubitObject::TABLE_NAME.' obj
         ON io.id = obj.id
       JOIN '.QubitSlug::TABLE_NAME.' slug
         ON io.id = slug.object_id
       JOIN '.QubitStatus::TABLE_NAME.' pubstat
         ON io.id = pubstat.object_id
       LEFT JOIN '.QubitDigitalObject::TABLE_NAME.' do
         ON io.id = do.object_id
       WHERE io.id = :id';

      self::$statements['informationObject'] = self::$conn->prepare($sql);
    }

    // Do select
    self::$statements['informationObject']->execute(array(':id' => $id));

    // Get first result
    $this->data = self::$statements['informationObject']->fetch(PDO::FETCH_ASSOC);

    if (false === $this->data)
    {
      throw new sfException("Couldn't find information object (id: $id)");
    }

    // Load event data
    $this->loadEvents();

    return $this;
  }

  /**
   * Return an array of ancestors
   *
   * @return array of ancestors
   */
  public function getAncestors()
  {
    if (!isset($this->ancestors))
    {
      // Find ancestors
      $sql  = 'SELECT id, identifier, repository_id';
      $sql .= ' FROM '.QubitInformationObject::TABLE_NAME.' node';
      $sql .= ' WHERE node.lft < ? AND node.rgt > ?';
      $sql .= ' ORDER BY lft';

      $this->ancestors = QubitPdo::fetchAll(
        $sql,
        array($this->__get('lft'), $this->__get('rgt')),
        array('fetchMode' => PDO::FETCH_ASSOC)
      );
    }

    if (!isset($this->ancestors) || empty($this->ancestors))
    {
      throw new sfException(sprintf("%s: Couldn't find ancestors, please make sure lft and rgt values are correct", get_class($this)));
    }

    return $this->ancestors;
  }

  /**
   * Return an array of children
   *
   * @return array of children
   */
  public function getChildren()
  {
    if (!isset($this->children))
    {
      // Find children
      $sql  = 'SELECT
                  node.id';
      $sql .= ' FROM '.QubitInformationObject::TABLE_NAME.' node';
      $sql .= ' WHERE node.parent_id = :id';
      $sql .= ' ORDER BY lft';

      $this->children = QubitPdo::fetchAll($sql, array(':id' => $this->id));
    }

    return $this->children;
  }

  /**
   * Return the closest repository
   *
   * @return QubitRepository
   */
  public function getRepository()
  {
    if (!isset($this->repository))
    {
      if ($this->__isset('repository_id'))
      {
        $this->repository = QubitRepository::getById($this->__get('repository_id'));
      }
      else
      {
        if (is_array($this->getAncestors()) && count($this->getAncestors()) > 0)
        {
          foreach (array_reverse($this->getAncestors()) as $item)
          {
            if (isset($item['repository_id']))
            {
              $this->repository = QubitRepository::getById($item['repository_id']);

              break;
            }
          }
        }
        else
        {
          $this->repository = null;
        }
      }
    }

    return $this->repository;
  }

  public function getClosestCreators()
  {
    $inheritedCreators = array();

    if (!is_array($this->getAncestors()) || count($this->getAncestors()) == 0)
    {
      return $inheritedCreators;
    }

    if (!isset(self::$statements['inheritedCreators']))
    {
      $sql  = 'SELECT
                  event.actor_id as id';
      $sql .= ' FROM '.QubitEvent::TABLE_NAME.' event';
      $sql .= ' WHERE event.actor_id IS NOT NULL';
      $sql .= ' AND event.object_id = ?';
      $sql .= ' AND event.type_id = ?';

      self::$statements['inheritedCreators'] = self::$conn->prepare($sql);
    }

    foreach (array_reverse($this->getAncestors()) as $ancestor)
    {
      self::$statements['inheritedCreators']->execute(array($ancestor['id'], QubitTerm::CREATION_ID));

      foreach (self::$statements['inheritedCreators']->fetchAll(PDO::FETCH_OBJ) as $creator)
      {
        $inheritedCreators[] = $creator;
      }

      if (count($inheritedCreators) > 0)
      {
        break;
      }
    }

    return $inheritedCreators;
  }

  public function getLevelOfDescription($culture)
  {
    if (!isset(self::$lookups['levelOfDescription']))
    {
      $criteria = new Criteria;
      $criteria->add(QubitTerm::TAXONOMY_ID, QubitTaxonomy::LEVEL_OF_DESCRIPTION_ID);
      foreach (QubitTerm::get($criteria) as $item)
      {
        self::$lookups['levelOfDescription'][$item->id] = $item;
      }
    }

    if (isset(self::$lookups['levelOfDescription'][$this->__get('level_of_description_id')]))
    {
      return self::$lookups['levelOfDescription'][$this->__get('level_of_description_id')]->getName(array(
        'culture' => $culture,
        'fallback' => true));
    }
  }

  public function getMediaTypeName($culture)
  {
    if (!$this->__isset('media_type_id'))
    {
      return;
    }

    if (0 == count(self::$lookups['mediaType']))
    {
      $criteria = new Criteria;
      $criteria->add(QubitTerm::TAXONOMY_ID, QubitTaxonomy::MEDIA_TYPE_ID);
      foreach (QubitTerm::get($criteria) as $item)
      {
        self::$lookups['mediaType'][$item->id] = $item;
      }
    }

    if (isset(self::$lookups['mediaType'][$this->__get('media_type_id')]))
    {
      return self::$lookups['mediaType'][$this->__get('media_type_id')]->getName(array(
        'culture' => $culture,
        'fallback' => true));
    }
  }

  public function getMimeType()
  {
    if (!$this->__isset('digital_object_id'))
    {
      return;
    }

    if (null !== $digitalObject = QubitDigitalObject::getById($this->__get('digital_object_id')))
    {
      return $digitalObject->getMimeType();
    }
  }

  /**
   * Get full reference code, with optional country code and repository prefixes as well.
   *
   * @param bool $includeRepoAndCountry  Whether or not to prepend country code and repository identifier
   * @return string  The full reference code
   */
  public function getReferenceCode($includeRepoAndCountry = true)
  {
    if (null == $this->__get('identifier'))
    {
      return;
    }

    $refcode = '';
    $this->repository = $this->getRepository();

    if (isset($this->repository) && $includeRepoAndCountry)
    {
      if (null != $cc = $this->repository->getCountryCode(array('culture' => $this->__get('culture'))))
      {
        $refcode .= $cc.' ';
      }

      if (isset($this->repository->identifier))
      {
        $refcode .= $this->repository->identifier.' ';
      }
    }

    $identifiers = array();

    foreach ($this->getAncestors() as $item)
    {
      if (isset($item['identifier']))
      {
        $identifiers[] = $item['identifier'];
      }
    }

    if (isset($this->identifier))
    {
      $identifiers[] = $this->identifier;
    }

    $refcode .= implode(sfConfig::get('app_separator_character', '-'), $identifiers);

    return $refcode;
  }

  protected function loadEvents()
  {
    if (!isset($this->events))
    {
      $events = array();

      if (!isset(self::$statements['event']))
      {
        $sql  = 'SELECT
                    event.id,
                    event.start_date,
                    event.end_date,
                    event.actor_id,
                    event.type_id,
                    event.source_culture,
                    i18n.date,
                    i18n.culture';
        $sql .= ' FROM '.QubitEvent::TABLE_NAME.' event';
        $sql .= ' JOIN '.QubitEventI18n::TABLE_NAME.' i18n
                    ON event.id = i18n.id';
        $sql .= ' WHERE event.object_id = ?';

        self::$statements['event'] = self::$conn->prepare($sql);
      }

      self::$statements['event']->execute(array($this->__get('id')));

      foreach (self::$statements['event']->fetchAll() as $item)
      {
        if (!isset($events[$item['id']]))
        {
          $event = new stdClass;
          $event->id = $item['id'];
          $event->start_date = $item['start_date'];
          $event->end_date = $item['end_date'];
          $event->actor_id = $item['actor_id'];
          $event->type_id = $item['type_id'];
          $event->source_culture = $item['source_culture'];

          $events[$item['id']] = $event;
        }

        $events[$item['id']]->dates[$item['culture']] = $item['date'];
      }

      $this->events = $events;
    }

    return $this->events;
  }

  protected function getDates($field, $culture)
  {
    $dates = array();

    if (0 < count($this->events))
    {
      foreach ($this->events as $item)
      {
        switch($field)
        {
          case 'start_date':
          case 'end_date':
            if (isset($item->$field))
            {
              $date = new DateTime($item->$field);
              $dates[] = $date->format('Ymd');
            }

            break;

          case 'date':
            if (isset($item->dates[$culture]))
            {
              $dates[] = $item->dates[$culture];
            }
            else if (isset($item->start_date) || isset($item->end_date))
            {
              $dates[] = Qubit::renderDateStartEnd(null, $item->start_date, $item->end_date);
            }

            break;

          case 'array':

            $tmp = array();

            if (isset($item->date) && isset($item->dates[$culture]))
            {
              $tmp['date'] = $item->dates[$culture];
            }

            if (isset($item->start_date))
            {
              $tmp['startDate'] = arElasticSearchPluginUtil::convertDate($item->start_date);
              $tmp['startDateString'] = Qubit::renderDate($item->start_date);
            }

            if (isset($item->end_date))
            {
              $tmp['endDate'] = arElasticSearchPluginUtil::convertDate($item->end_date);
              $tmp['endDateString'] = Qubit::renderDate($item->end_date);
            }

            $tmp['typeId'] = $item->type_id;

            $dates[] = $tmp;

            break;
        }
      }
    }

    return $dates;
  }

  public function getActors($options = array())
  {
    $actors = array();

    if (!isset(self::$statements['actor']))
    {
      $sql  = 'SELECT
                  actor.id,
                  actor.entity_type_id,
                  slug.slug';
      $sql .= ' FROM '.QubitActor::TABLE_NAME.' actor';
      $sql .= ' JOIN '.QubitSlug::TABLE_NAME.' slug
                  ON actor.id = slug.object_id';
      $sql .= ' WHERE actor.id = ?';

      self::$statements['actor'] = self::$conn->prepare($sql);
    }

    if (0 < count($this->events))
    {
      foreach ($this->events as $item)
      {
        if (isset($item->actor_id))
        {
          // Filter by type
          if (isset($options['typeId']) && $options['typeId'] != $item->type_id)
          {
            continue;
          }

          self::$statements['actor']->execute(array($item->actor_id));

          if ($actor = self::$statements['actor']->fetch(PDO::FETCH_OBJ))
          {
            $actors[] = $actor;
          }
        }
      }
    }

    return $actors;
  }

  public function getNameAccessPoints()
  {
    $names = array();

    // Subject relations
    if (!isset(self::$statements['actorRelation']))
    {
      $sql  = 'SELECT actor.id';
      $sql .= ' FROM '.QubitActor::TABLE_NAME.' actor';
      $sql .= ' JOIN '.QubitRelation::TABLE_NAME.' relation
                  ON actor.id = relation.object_id';
      $sql .= ' WHERE relation.subject_id = :resourceId
                  AND relation.type_id = :typeId';

      self::$statements['actorRelation'] = self::$conn->prepare($sql);
    }

    self::$statements['actorRelation']->execute(array(
      ':resourceId' => $this->__get('id'),
      ':typeId' => QubitTerm::NAME_ACCESS_POINT_ID));

    foreach (self::$statements['actorRelation']->fetchAll(PDO::FETCH_OBJ) as $item)
    {
      $names[$item->id] = $item;
    }

    return $names;
  }

  /*
   * Prepare term query.
   */
  protected function prepareRelatedTermsQuery()
  {
    if (!isset(self::$statements['relatedTerms']))
    {
      $sql  = 'SELECT
                  DISTINCT current.id';
      $sql .= ' FROM '.QubitObjectTermRelation::TABLE_NAME.' otr';
      $sql .= ' JOIN '.QubitTerm::TABLE_NAME.' current
                  ON otr.term_id = current.id';
      $sql .= ' WHERE otr.object_id = :id
                  AND current.taxonomy_id = :taxonomyId';

      self::$statements['relatedTerms'] = self::$conn->prepare($sql);
    }
  }

  /*
   * Get related terms plus any ancestors
   */
  protected function getRelatedTerms($typeId)
  {
    $relatedTerms = array();

    $this->prepareRelatedTermsQuery();

    self::$statements['relatedTerms']->execute(array(':id' => $this->__get('id'), ':taxonomyId' => $typeId));

    // Get directly related terms.
    $rows = self::$statements['relatedTerms']->fetchAll(PDO::FETCH_ASSOC);

    if (0 == count($rows))
    {
      return $relatedTerms;
    }

    // Iterate over each directly related term, adding all ancestors of each
    foreach($rows as $row)
    {
      $relatedTerms = array_merge($relatedTerms, $this->recursivelyGetParentTerms($row['id']));
    }

    $relatedTerms = array_unique($relatedTerms);

    return $relatedTerms;
  }

  /**
   * Recursively find all parent terms for any given term. Subject, Place and
   * Genre terms are preloaded in array $this->termParentList. The import array $ids is
   * appended to for every parent that is added on each recursive call.
   *
   * @param array $id  The term id to find the parents for.
   * @return array  Array with directly related term first, followed by
   *                any parents found, in child to top parent order.
   */
  private function recursivelyGetParentTerms($id)
  {
    if (null === $parent = $this->termParentList[$id])
    {
      return array($id);
    }

    // Do not include root term id.
    if (QubitTerm::ROOT_ID == $parent)
    {
      return array($id);
    }

    return array_merge(array($id), $this->recursivelyGetParentTerms($parent));
  }

  /*
   * Get directly related terms
   */
  protected function getDirectlyRelatedTerms($typeId)
  {
    $this->prepareRelatedTermsQuery();
    self::$statements['relatedTerms']->execute(array(':id' => $this->__get('id'), ':taxonomyId' => $typeId));

    return self::$statements['relatedTerms']->fetchAll(PDO::FETCH_ASSOC);
  }

  protected function getLanguagesAndScripts()
  {
    // Find langs and scripts
    if (!isset(self::$statements['langAndScript']))
    {
      $sql  = 'SELECT
                  node.name,
                  i18n.value';
      $sql .= ' FROM '.QubitProperty::TABLE_NAME.' node';
      $sql .= ' JOIN '.QubitPropertyI18n::TABLE_NAME.' i18n
                  ON node.id = i18n.id';
      $sql .= ' WHERE node.source_culture = i18n.culture
                  AND node.object_id = ?
                  AND (node.name = ? OR node.name = ?)';

      self::$statements['langAndScript'] = self::$conn->prepare($sql);
    }

    self::$statements['langAndScript']->execute(array(
      $this->__get('id'),
      'language',
      'script'));

    // Add to arrays
    foreach (self::$statements['langAndScript']->fetchAll(PDO::FETCH_OBJ) as $item)
    {
      $codes = unserialize($item->value);

      if (0 < count($codes))
      {
        switch ($item->name)
        {
          case 'language':
            $this->languages = $codes;

            break;

          case 'script':
            $this->scripts = $codes;

            break;
        }
      }
    }

    return $this;
  }

  public function getNotes()
  {
    $notes = array();

    // Subject relations
    if (!isset(self::$statements['note']))
    {
      $sql  = 'SELECT
                  i18n.content';
      $sql .= ' FROM '.QubitNote::TABLE_NAME.' note';
      $sql .= ' WHERE note.object_id = ?';

      self::$statements['note'] = self::$conn->prepare($sql);
    }

    self::$statements['note']->execute(array(
      $this->__get('id')));

    foreach (self::$statements['note']->fetchAll(PDO::FETCH_OBJ) as $item)
    {
      if (0 < strlen($item->content))
      {
        $notes[] = $item->content;
      }
    }

    if (0 < count($notes))
    {
      return implode(' ', $notes);
    }
  }

  public function getNotesByType($typeId)
  {
    $sql = 'SELECT
              id, source_culture
              FROM '.QubitNote::TABLE_NAME.
              ' WHERE object_id = ? AND type_id = ?';

    return QubitPdo::fetchAll($sql, array($this->__get('id'), $typeId));
  }

  public function getTermIdByNameAndTaxonomy($name, $taxonomyId, $culture = 'en')
  {
    $sql = 'SELECT t.id
              FROM term t
              LEFT JOIN term_i18n ti
              ON t.id=ti.id
              WHERE t.taxonomy_id=? AND ti.name=? AND ti.culture=?';

    return QubitPdo::fetchColumn($sql, array($taxonomyId, $name, $culture));
  }

  public function getThumbnailPath()
  {
    if (!$this->__isset('digital_object_id'))
    {
      return;
    }

    $criteria = new Criteria;
    $criteria->add(QubitDigitalObject::PARENT_ID, $this->__get('digital_object_id'));
    $criteria->add(QubitDigitalObject::USAGE_ID, QubitTerm::THUMBNAIL_ID);

    if (null !== $thumbnail = QubitDigitalObject::getOne($criteria))
    {
      return $thumbnail->getFullPath();
    }
  }

  public function getDigitalObjectAltText()
  {
    if (!$this->__isset('digital_object_id'))
    {
      return;
    }

    $criteria = new Criteria;
    $criteria->add(QubitDigitalObject::PARENT_ID, $this->__get('digital_object_id'));

    if (null !== $do = QubitDigitalObject::getOne($criteria))
    {
      return $do->getDigitalObjectAltText();
    }
  }

  public function getStorageNames()
  {
    $names = array();

    // Subject relations
    if (!isset(self::$statements['storageName']))
    {
      $sql  = 'SELECT i18n.name';
      $sql .= ' FROM '.QubitRelation::TABLE_NAME.' rel';
      $sql .= ' WHERE rel.object_id = :resource_id';
      $sql .= '   AND rel.type_id = :type_id';

      self::$statements['storageName'] = self::$conn->prepare($sql);
    }

    self::$statements['storageName']->execute(array(
      ':resource_id' => $this->__get('id'),
      ':type_id' => QubitTerm::HAS_PHYSICAL_OBJECT_ID));

    foreach (self::$statements['storageName']->fetchAll(PDO::FETCH_OBJ) as $item)
    {
      if (0 < strlen($item->name))
      {
        $names[] = $item->name;
      }
    }

    if (0 < count($names))
    {
      return implode(' ', $names);
    }
  }

  public function getRights()
  {
    if (!isset(self::$statements['rights']))
    {
      $sql  = 'SELECT
                  rights.*, rightsi18n.*';
      $sql .= ' FROM '.QubitRights::TABLE_NAME.' rights';
      $sql .= ' JOIN '.QubitRightsI18n::TABLE_NAME.' rightsi18n
                  ON rights.id = rightsi18n.id';
      $sql .= ' JOIN '.QubitRelation::TABLE_NAME.' rel
                  ON rights.id = rel.object_id';
      $sql .= ' WHERE rel.subject_id = ?';
      $sql .= ' AND rel.type_id = '.QubitTerm::RIGHT_ID;

      self::$statements['rights'] = self::$conn->prepare($sql);
    }

    self::$statements['rights']->execute(array(
      $this->__get('id')));

    return self::$statements['rights']->fetchAll(PDO::FETCH_CLASS);
  }

  public function getGrantedRights()
  {
    if (!isset(self::$statements['grantedRights']))
    {
      $sql  = 'SELECT
                  gr.*';
      $sql .= ' FROM '.QubitGrantedRight::TABLE_NAME.' gr';
      $sql .= ' JOIN '.QubitRelation::TABLE_NAME.' rel
                  ON gr.rights_id = rel.object_id';
      $sql .= ' WHERE rel.subject_id = ?';
      $sql .= ' AND rel.type_id = '.QubitTerm::RIGHT_ID;

      self::$statements['grantedRights'] = self::$conn->prepare($sql);
    }

    self::$statements['grantedRights']->execute(array($this->__get('id')));

    return self::$statements['grantedRights']->fetchAll(PDO::FETCH_CLASS);
  }

  /**
   * Get text transcript, if one exists
   */
  public function getTranscript()
  {
    if (!$this->__isset('digital_object_id'))
    {
      return false;
    }

    if (!isset(self::$statements['transcript']))
    {
      $sql  = 'SELECT i18n.value
        FROM '.QubitProperty::TABLE_NAME.' property
        JOIN '.QubitPropertyI18n::TABLE_NAME.' i18n
          ON property.id = i18n.id
        WHERE property.name = "transcript"
          AND property.source_culture = i18n.culture
          AND property.object_id = ?';

      self::$statements['transcript'] = self::$conn->prepare($sql);
    }

    self::$statements['transcript']->execute(array($this->__get('digital_object_id')));

    return self::$statements['transcript']->fetchColumn();
  }

  /**
   * Get finding aid text transcript, if one exists
   */
  public function getFindingAidTranscript()
  {
    if (!isset(self::$statements['findingAidTranscript']))
    {
      $sql  = 'SELECT i18n.value
        FROM '.QubitProperty::TABLE_NAME.' property
        JOIN '.QubitPropertyI18n::TABLE_NAME.' i18n
          ON property.id = i18n.id
        WHERE property.name = "findingAidTranscript"
          AND property.source_culture = i18n.culture
          AND property.object_id = ?';

      self::$statements['findingAidTranscript'] = self::$conn->prepare($sql);
    }

    self::$statements['findingAidTranscript']->execute(array($this->__get('id')));

    return self::$statements['findingAidTranscript']->fetchColumn();
  }

  /**
   * Get finding aid status
   */
  public function getFindingAidStatus()
  {
    if (!isset(self::$statements['findingAidStatus']))
    {
      $sql  = 'SELECT i18n.value
        FROM '.QubitProperty::TABLE_NAME.' property
        JOIN '.QubitPropertyI18n::TABLE_NAME.' i18n
          ON property.id = i18n.id
        WHERE property.name = "findingAidStatus"
          AND property.source_culture = i18n.culture
          AND property.object_id = ?';

      self::$statements['findingAidStatus'] = self::$conn->prepare($sql);
    }

    self::$statements['findingAidStatus']->execute(array($this->__get('id')));

    return self::$statements['findingAidStatus']->fetchColumn();
  }

  protected function getAlternativeIdentifiers()
  {
    // Find langs and scripts
    if (!isset(self::$statements['alternativeIdentifiers']))
    {
      $sql  = 'SELECT
                  node.name,
                  i18n.value';
      $sql .= ' FROM '.QubitProperty::TABLE_NAME.' node';
      $sql .= ' JOIN '.QubitPropertyI18n::TABLE_NAME.' i18n
                  ON node.id = i18n.id';
      $sql .= ' WHERE node.source_culture = i18n.culture
                  AND node.object_id = ?
                  AND node.scope = ?';

      self::$statements['alternativeIdentifiers'] = self::$conn->prepare($sql);
    }

    self::$statements['alternativeIdentifiers']->execute(array(
      $this->__get('id'),
      'alternativeIdentifiers'));

    $alternativeIdentifiers = array();
    foreach (self::$statements['alternativeIdentifiers']->fetchAll() as $item)
    {
      $tmp = array();

      $tmp['label'] = $item['name'];
      $tmp['identifier'] = $item['value'];

      $alternativeIdentifiers[] = $tmp;
    }

    return $alternativeIdentifiers;
  }

  protected function getPropertyValue($name)
  {
    $sql  = 'SELECT
                i18n.value';
    $sql .= ' FROM '.QubitProperty::TABLE_NAME.' node';
    $sql .= ' JOIN '.QubitPropertyI18n::TABLE_NAME.' i18n
                ON node.id = i18n.id';
    $sql .= ' WHERE node.source_culture = i18n.culture
                AND node.object_id = ?
                AND node.name = ?';

    self::$statements['propertyValue'] = self::$conn->prepare($sql);
    self::$statements['propertyValue']->execute(array($this->__get('id'), $name));
    $result = self::$statements['propertyValue']->fetch(PDO::FETCH_ASSOC);

    if(false !== $result)
    {
      return $result['value'];
    }
  }

  protected function getProperty($name)
  {
    $sql  = 'SELECT
                prop.id, prop.source_culture';
    $sql .= ' FROM '.QubitProperty::TABLE_NAME.' prop';
    $sql .= ' WHERE prop.object_id = ?
                AND prop.name = ?';

    self::$statements['property'] = self::$conn->prepare($sql);
    self::$statements['property']->execute(array($this->__get('id'), $name));

    return self::$statements['property']->fetch(PDO::FETCH_OBJ);
  }

  protected function getAips()
  {
    $sql  = 'SELECT
                aip.id';
    $sql .= ' FROM '.QubitAip::TABLE_NAME.' aip';
    $sql .= ' JOIN '.QubitRelation::TABLE_NAME.' relation
                ON aip.id = relation.subject_id';
    $sql .= ' WHERE relation.object_id = ?
                AND relation.type_id = ?';

    self::$statements['aips'] = self::$conn->prepare($sql);
    self::$statements['aips']->execute(array($this->__get('id'), QubitTerm::AIP_RELATION_ID));

    return self::$statements['aips']->fetchAll(PDO::FETCH_OBJ);
  }

  protected function getPhysicalObjects()
  {
    $sql  = 'SELECT
                phys.id,
                phys.source_culture';
    $sql .= ' FROM '.QubitPhysicalObject::TABLE_NAME.' phys';
    $sql .= ' JOIN '.QubitRelation::TABLE_NAME.' relation
                ON phys.id = relation.subject_id';
    $sql .= ' WHERE relation.object_id = ?
                AND relation.type_id = ?';

    self::$statements['physicalObjects'] = self::$conn->prepare($sql);
    self::$statements['physicalObjects']->execute(array($this->__get('id'), QubitTerm::HAS_PHYSICAL_OBJECT_ID));

    return self::$statements['physicalObjects']->fetchAll(PDO::FETCH_OBJ);
  }

  private function getBasisRights()
  {
    $basisRights = array();

    foreach ($this->getRights() as $right)
    {
      $basisRight = array();

      $basisRight['startDate'] = arElasticSearchPluginUtil::normalizeDateWithoutMonthOrDay($right->start_date);
      $basisRight['endDate'] = arElasticSearchPluginUtil::normalizeDateWithoutMonthOrDay($right->end_date, true);
      $basisRight['rightsNote'] = $right->rights_note;
      $basisRight['licenseTerms'] = $right->license_terms;

      if ($right->rights_holder_id)
      {
        $basisRight['rightsHolder'] = QubitActor::getById($right->rights_holder_id)->getAuthorizedFormOfName();
      }

      if ($right->basis_id)
      {
        $basisRight['basis'] = QubitTerm::getById($right->basis_id)->getName();
      }

      if ($right->copyright_status_id)
      {
        $basisRight['copyrightStatus'] = QubitTerm::getById($right->copyright_status_id)->getName();
      }

      $basisRights[] = $basisRight;
    }

    return $basisRights;
  }

  private function getActRights()
  {
    $actRights = array();
    foreach ($this->getGrantedRights() as $grantedRight)
    {
      $actRight = array();

      if ($grantedRight->act_id)
      {
        $actRight['act'] = QubitTerm::getById($grantedRight->act_id)->getName();
      }

      $actRight['restriction'] = QubitGrantedRight::getRestrictionString($grantedRight->restriction);
      $actRight['startDate'] = arElasticSearchPluginUtil::normalizeDateWithoutMonthOrDay($grantedRight->start_date);
      $actRight['endDate'] = arElasticSearchPluginUtil::normalizeDateWithoutMonthOrDay($grantedRight->end_date, true);

      $actRights[] = $actRight;
    }

    return $actRights;
  }

  public function serialize()
  {
    $serialized = array();

    // Add default null values to allow document updates using partial data.
    // To remove fields from the document is required the use of scripts, which
    // requires global configuration changes or deployments headaches. If there
    // is not a default value set in the mapping configuration, null values work
    // the same as missing fields in almost every case and allow us to 'remove'
    // fields without using scripts in partial updates.
    $serialized['findingAid'] = array(
      'transcript' => null,
      'status' => null
    );

    $serialized['id'] = $this->id;
    $serialized['slug'] = $this->slug;
    $serialized['parentId'] = $this->parent_id;
    $serialized['identifier'] = $this->identifier;
    $serialized['referenceCode'] = $this->getReferenceCode();
    $serialized['referenceCodeWithoutCountryAndRepo'] = $this->getReferenceCode(false);
    $serialized['levelOfDescriptionId'] = $this->level_of_description_id;
    $serialized['publicationStatusId'] = $this->publication_status_id;
    $serialized['lft'] = $this->lft;

    // Alternative identifiers
    $alternativeIdentifiers = $this->getAlternativeIdentifiers();
    if (0 < count($alternativeIdentifiers))
    {
      $serialized['alternativeIdentifiers'] = $alternativeIdentifiers;
    }

    // NB: this will include the ROOT_ID
    $serialized['ancestors'] = array_column($this->getAncestors(), 'id');

    // NB: this should be an ordered array
    foreach ($this->getChildren() as $child)
    {
      $serialized['children'][] = $child->id;
    }

    // Copyright status
    $statusId = null;
    foreach ($this->getRights() as $item)
    {
      if (isset($item->copyright_status_id))
      {
        $statusId = $item->copyright_status_id;
        break;
      }
    }
    if (null !== $statusId)
    {
      $serialized['copyrightStatusId'] = $statusId;
    }

    // Material type
    foreach ($this->getDirectlyRelatedTerms(QubitTaxonomy::MATERIAL_TYPE_ID) as $item)
    {
      $serialized['materialTypeId'][] = $item['id'];
    }

    // Make sure that media_type_id gets a value in case that one was not
    // assigned, which seems to be a possibility when using the offline usage.
    if (null === $this->media_type_id && $this->usage_id == QubitTerm::OFFLINE_ID)
    {
      $this->media_type_id = QubitTerm::OTHER_ID;
    }

    // Media
    if ($this->media_type_id)
    {
      $serialized['digitalObject']['mediaTypeId'] = $this->media_type_id;
      $serialized['digitalObject']['usageId'] = $this->usage_id;
      $serialized['digitalObject']['filename'] = $this->filename;
      $serialized['digitalObject']['thumbnailPath'] = $this->getThumbnailPath();
      $serialized['digitalObject']['digitalObjectAltText'] = $this->getDigitalObjectAltText();

      $serialized['hasDigitalObject'] = true;
    }
    else
    {
      $serialized['hasDigitalObject'] = false;
    }

    // Dates
    foreach ($this->events as $event)
    {
      $serialized['dates'][] = arElasticSearchEvent::serialize($event);

      // The dates indexed above are nested objects and that complicates sorting.
      // Additionally, we only show the first populated dates on the search
      // results. Indexing the first populated dates on different fields makes
      // sorting easier and more intuitive.
      if (isset($serialized['startDateSort']) || isset($serialized['endDateSort']))
      {
        continue;
      }

      if (!empty($event->start_date))
      {
        $serialized['startDateSort'] = arElasticSearchPluginUtil::normalizeDateWithoutMonthOrDay($event->start_date);
      }

      if (!empty($event->end_date))
      {
        $serialized['endDateSort'] = arElasticSearchPluginUtil::normalizeDateWithoutMonthOrDay($event->end_date, true);
      }
    }

    // Transcript
    if (false !== $transcript = $this->getTranscript())
    {
      $serialized['transcript'] = $transcript;
    }

    // Finding aid transcript
    if (false !== $findingAidTranscript = $this->getFindingAidTranscript())
    {
      $serialized['findingAid']['transcript'] = $findingAidTranscript;
    }

    // Finding aid status
    if (false !== $findingAidStatus = $this->getFindingAidStatus())
    {
      $serialized['findingAid']['status'] = (integer)$findingAidStatus;
    }

    // Repository
    if (null !== $repository = $this->getRepository())
    {
      $serialized['repository'] = arElasticSearchRepository::serialize($repository);
    }

    // Places
    foreach ($this->getRelatedTerms(QubitTaxonomy::PLACE_ID) as $item)
    {
      $node = new arElasticSearchTermPdo($item);
      $serialized['places'][] = $node->serialize();
    }

    foreach ($this->getDirectlyRelatedTerms(QubitTaxonomy::PLACE_ID) as $item)
    {
      $serialized['directPlaces'][] = $item['id'];
    }

    // Subjects
    foreach ($this->getRelatedTerms(QubitTaxonomy::SUBJECT_ID) as $item)
    {
      $node = new arElasticSearchTermPdo($item);
      $serialized['subjects'][] = $node->serialize();
    }

    foreach ($this->getDirectlyRelatedTerms(QubitTaxonomy::SUBJECT_ID) as $item)
    {
      $serialized['directSubjects'][] = $item['id'];
    }

    // Name access points
    foreach ($this->getNameAccessPoints() as $item)
    {
      $node = new arElasticSearchActorPdo($item->id);
      $serialized['names'][] = $node->serialize();
    }

    // Genres
    foreach ($this->getRelatedTerms(QubitTaxonomy::GENRE_ID) as $item)
    {
      $node = new arElasticSearchTermPdo($item);
      $serialized['genres'][] = $node->serialize();
    }

    foreach ($this->getDirectlyRelatedTerms(QubitTaxonomy::GENRE_ID) as $item)
    {
      $serialized['directGenres'][] = $item['id'];
    }

    // Creators
    foreach ($this->creators as $item)
    {
      $node = new arElasticSearchActorPdo($item->id);
      $serialized['creators'][] = $node->serialize();
    }

    // Inherited creators
    foreach ($this->inheritedCreators as $item)
    {
      $node = new arElasticSearchActorPdo($item->id);
      $serialized['inheritedCreators'][] = $node->serialize();
    }

    // Physical objects
    foreach ($this->getPhysicalObjects() as $item)
    {
      $serialized['physicalObjects'][] = arElasticSearchPhysicalObject::serialize($item);
    }

    // Notes
    foreach ($this->getNotesByType(QubitTerm::GENERAL_NOTE_ID) as $item)
    {
      $serialized['generalNotes'][] = arElasticSearchNote::serialize($item);
    }

    // PREMIS data
    if (null !== $premisData = arElasticSearchPluginUtil::getPremisData($this->id, self::$conn))
    {
      $serialized['metsData'] = $premisData;
    }

    if (null !== $termId = $this->getTermIdByNameAndTaxonomy('Alpha-numeric designations', QubitTaxonomy::RAD_NOTE_ID))
    {
      foreach ($this->getNotesByType($termId) as $item)
      {
        $serialized['alphaNumericNotes'][] = arElasticSearchNote::serialize($item);
      }
    }

    if (null !== $termId = $this->getTermIdByNameAndTaxonomy('Conservation', QubitTaxonomy::RAD_NOTE_ID))
    {
      foreach ($this->getNotesByType($termId) as $item)
      {
        $serialized['conservationNotes'][] = arElasticSearchNote::serialize($item);
      }
    }

    if (null !== $termId = $this->getTermIdByNameAndTaxonomy('Physical description', QubitTaxonomy::RAD_NOTE_ID))
    {
      foreach ($this->getNotesByType($termId) as $item)
      {
        $serialized['physicalDescriptionNotes'][] = arElasticSearchNote::serialize($item);
      }
    }

    if (null !== $termId = $this->getTermIdByNameAndTaxonomy('Continuation of title', QubitTaxonomy::RAD_TITLE_NOTE_ID))
    {
      foreach ($this->getNotesByType($termId) as $item)
      {
        $serialized['continuationOfTitleNotes'][] = arElasticSearchNote::serialize($item);
      }
    }

    if (null !== $termId = $this->getTermIdByNameAndTaxonomy("Archivist's note", QubitTaxonomy::NOTE_TYPE_ID))
    {
      foreach ($this->getNotesByType($termId) as $item)
      {
        $serialized['archivistsNotes'][] = arElasticSearchNote::serialize($item);
      }
    }

    if (false !== $item = $this->getProperty('titleStatementOfResponsibility'))
    {
      $serialized['titleStatementOfResponsibility'] = arElasticSearchProperty::serialize($item);
    }

    // Aips
    foreach ($this->getAips() as $item)
    {
      $node = new arElasticSearchAipPdo($item->id);
      $serialized['aip'][] = $node->serialize();
    }

    $serialized['actRights'] = $this->getActRights();
    $serialized['basisRights'] = $this->getBasisRights();

    $serialized['createdAt'] = arElasticSearchPluginUtil::convertDate($this->created_at);
    $serialized['updatedAt'] = arElasticSearchPluginUtil::convertDate($this->updated_at);

    $serialized['sourceCulture'] = $this->source_culture;
    $serialized['i18n'] = arElasticSearchModelBase::serializeI18ns($this->id, array('QubitInformationObject'));

    // Add "Part of" information if this isn't a top level description
    if (count($this->ancestors) > 1)
    {
      $collectionRootId = $this->ancestors[1]['id'];
      $rootSlug = QubitPdo::fetchColumn('SELECT slug FROM slug WHERE object_id=?', array($collectionRootId));

      if (!$rootSlug)
      {
        throw new sfException("No slug found for information object $collectionRootId");
      }

      $rootSourceCulture = QubitPdo::fetchColumn('SELECT source_culture FROM information_object WHERE id=?',
                                                 array($collectionRootId));
      if (!$rootSourceCulture)
      {
        throw new sfException("No source culture found for information object $collectionRootId");
      }

      $i18nFields = arElasticSearchModelBase::serializeI18ns(
        $collectionRootId,
        array('QubitInformationObject'),
        array('fields' => array('title'))
      );

      $serialized['partOf']['id'] = $collectionRootId;
      $serialized['partOf']['sourceCulture'] = $rootSourceCulture;
      $serialized['partOf']['slug'] = $rootSlug;
      $serialized['partOf']['i18n'] = $i18nFields;
    }

    return $serialized;
  }
}
