  <did>
    <?php $objects = $$resourceVar->getPhysicalObjects() ?>
    <?php foreach($objects as $object): ?>
    <physloc>
      <?php if($object->location): ?>
      <title><?php echo esc_specialchars($object->location) ?></title>
      <?php endif; ?>
      <container type="<?php echo $object->type ?>">
      <?php echo esc_specialchars($object->name) ?>
      </container>
    </physloc>
    <?php endforeach; ?>
    <?php if (0 < strlen($value = $$resourceVar->getPropertyByName('titleProperOfPublishersSeries')->__toString())): ?>
    <unittitle><bibseries><title><?php echo esc_specialchars($value) ?></title></bibseries></unittitle>
    <?php endif; ?>
    <?php if (0 < strlen($value = $$resourceVar->getPropertyByName('parallelTitleOfPublishersSeries')->__toString())): ?>
    <unittitle><bibseries><title type="parallel"><?php echo esc_specialchars($value) ?></title></bibseries></unittitle>
    <?php endif; ?>
    <?php if (0 < strlen($value = $$resourceVar->getPropertyByName('otherTitleInformationOfPublishersSeries')->__toString())): ?>
    <unittitle><bibseries><title type="otherinfo"><?php echo esc_specialchars($value) ?></title></bibseries></unittitle>
    <?php endif; ?>
    <?php if (0 < strlen($value = $$resourceVar->getPropertyByName('statementOfResponsibilityRelatingToPublishersSeries')->__toString())): ?>
    <unittitle><bibseries><title type="statrep"><?php echo esc_specialchars($value) ?></title></bibseries></unittitle>
    <?php endif; ?>
    <?php if (0 < strlen($value = $$resourceVar->getPropertyByName('numberingWithinPublishersSeries')->__toString())): ?>
    <unittitle><bibseries><num><?php echo esc_specialchars($value) ?></num></bibseries></unittitle>
    <?php endif; ?>
    <?php if (0 < strlen($value = $$resourceVar->getTitle(array('cultureFallback' => true)))): ?>
    <unittitle encodinganalog="3.1.2"><?php echo esc_specialchars($value) ?></unittitle>
    <?php endif; ?>
    <?php if (0 < strlen($value = $$resourceVar->alternateTitle)): ?>
    <unittitle type="parallel"><?php echo esc_specialchars($value) ?></unittitle>
    <?php endif; ?>
    <?php if (0 < strlen($value = $$resourceVar->getPropertyByName('otherTitleInformation')->__toString())): ?>
    <unittitle type="otherinfo"><?php echo esc_specialchars($value) ?></unittitle>
    <?php endif; ?>
    <?php if (0 < strlen($value = $$resourceVar->getPropertyByName('titleStatementOfResponsibility')->__toString())): ?>
    <unittitle type="statrep"><?php echo esc_specialchars($value) ?></unittitle>
    <?php endif; ?>
    <?php if (0 < strlen($value = $$resourceVar->getEdition(array('cultureFallback' => true)))): ?>
    <unittitle><edition><?php echo esc_specialchars($value) ?></edition></unittitle>
    <?php endif; ?>
    <?php if (0 < strlen($value = $$resourceVar->getPropertyByName('editionStatementOfResponsibility')->__toString())): ?>
    <unittitle type="statrep"><edition><?php echo esc_specialchars($value) ?></edition></unittitle>
    <?php endif; ?>
    <?php if (0 < strlen($$resourceVar->getIdentifier())): ?>
    <unitid <?php if ($$resourceVar->getRepository()): ?><?php if ($repocode = $$resourceVar->getRepository()->getIdentifier()): ?><?php echo 'repositorycode="'.esc_specialchars($repocode).'" ' ?><?php endif; ?><?php if ($countrycode = $$resourceVar->getRepository()->getCountryCode()): ?><?php echo 'countrycode="'.$countrycode.'"' ?><?php endif;?><?php endif; ?> encodinganalog="3.1.1"><?php echo esc_specialchars($$resourceVar->getIdentifier()) ?></unitid>
    <?php endif; ?>
    <?php if (0 < strlen($value = $$resourceVar->getPropertyByName('standardNumber')->__toString())): ?>
    <unitid type="standard"><?php echo esc_specialchars($value) ?></unitid>
    <?php endif; ?>
    <?php foreach ($$resourceVar->getDates() as $date): ?>
    <?php if ($date->typeId != QubitTerm::CREATION_ID): ?>
    <unitdate <?php if ($type = $date->getType()->__toString()): ?><?php echo 'datechar="'.strtolower($type).'" ' ?><?php endif; ?><?php if ($startdate = $date->getStartDate()): ?><?php echo 'normal="'?><?php echo Qubit::renderDate($startdate) ?><?php if (0 < strlen($enddate = $date->getEndDate())): ?><?php echo '/'?><?php echo Qubit::renderDate($enddate) ?><?php endif; ?><?php echo '"' ?><?php endif; ?> encodinganalog="3.1.3"><?php echo esc_specialchars(Qubit::renderDateStartEnd($date->getDate(array('cultureFallback' => true)), $date->startDate, $date->endDate)) ?></unitdate>
    <?php endif; ?>
    <?php endforeach; // dates ?>
    <?php if (0 < strlen($value = $$resourceVar->getExtentAndMedium(array('cultureFallback' => true)))): ?>
    <physdesc>
      <extent encodinganalog="3.1.5"><?php echo esc_specialchars($value) ?></extent>
    </physdesc>
    <?php endif; ?>
    <?php if ($value = $$resourceVar->getRepository()): ?>
    <repository>
      <corpname><?php echo esc_specialchars($value->__toString()) ?></corpname>
      <?php if ($address = $value->getPrimaryContact()): ?>
      <address>
        <?php if (0 < strlen($addressline = $address->getStreetAddress())): ?>
        <addressline><?php echo esc_specialchars($addressline) ?></addressline>
        <?php endif; ?>
        <?php if (0 < strlen($addressline = $address->getCity())): ?>
        <addressline><?php echo esc_specialchars($addressline) ?></addressline>
        <?php endif; ?>
        <?php if (0 < strlen($addressline = $address->getRegion())): ?>
        <addressline><?php echo esc_specialchars($addressline) ?></addressline>
        <?php endif; ?>
        <?php if (0 < strlen($addressline = $$resourceVar->getRepositoryCountry())): ?>
        <addressline><?php echo esc_specialchars($addressline) ?></addressline>
        <?php endif; ?>
        <?php if (0 < strlen($addressline = $address->getPostalCode())): ?>
        <addressline><?php echo esc_specialchars($addressline) ?></addressline>
        <?php endif; ?>
        <?php if (0 < strlen($addressline = $address->getTelephone())): ?>
        <addressline><?php echo __('Telephone: ').esc_specialchars($addressline) ?></addressline>
        <?php endif; ?>
        <?php if (0 < strlen($addressline = $address->getFax())): ?>
        <addressline><?php echo __('Fax: ').esc_specialchars($addressline) ?></addressline>
        <?php endif; ?>
        <?php if (0 < strlen($addressline = $address->getEmail())): ?>
        <addressline><?php echo __('Email: ').esc_specialchars($addressline) ?></addressline>
        <?php endif; ?>
        <?php if (0 < strlen($addressline = $address->getWebsite())): ?>
        <addressline><?php echo esc_specialchars($addressline) ?></addressline>
        <?php endif; ?>
      </address>
      <?php endif; ?>
    </repository>
    <?php endif; ?>
    <?php if (0 < count($langmaterial = $$resourceVar->language)): ?>
    <langmaterial encodinganalog="3.4.3">
      <?php foreach ($langmaterial as $languageCode): ?>
      <language langcode="<?php echo ($iso6392 = $iso639convertor->getID3($languageCode)) ? strtolower($iso6392) : $languageCode ?>"><?php echo format_language($languageCode) ?></language><?php endforeach; ?>
    </langmaterial>
    <?php endif; ?>
    <?php if ($$resourceVar->sources): ?>
    <note type="sourcesDescription"><p><?php echo esc_specialchars($$resourceVar->sources) ?></p></note>
    <?php endif; ?>
    <?php if (0 < count($notes = $$resourceVar->getNotesByType(array('noteTypeId' => QubitTerm::GENERAL_NOTE_ID)))): ?><?php foreach ($notes as $note): ?><note type="<?php echo esc_specialchars($note->getType(array('cultureFallback' => true))) ?>" encodinganalog="3.6.1"><p><?php echo esc_specialchars($note->getContent(array('cultureFallback' => true))) ?></p></note><?php endforeach; ?><?php endif; ?>
    <?php if (0 < strlen($value = $$resourceVar->getPropertyByName('statementOfScaleCartographic')->__toString())): ?>
    <materialspec type='cartographic'><?php echo esc_specialchars($value) ?></materialspec>
    <?php endif; ?>
    <?php if (0 < strlen($value = $$resourceVar->getPropertyByName('statementOfProjection')->__toString())): ?>
    <materialspec type='projection'><?php echo esc_specialchars($value) ?></materialspec>
    <?php endif; ?>
    <?php if (0 < strlen($value = $$resourceVar->getPropertyByName('statementOfCoordinates')->__toString())): ?>
    <materialspec type='coordinates'><?php echo esc_specialchars($value) ?></materialspec>
    <?php endif; ?>
    <?php if (0 < strlen($value = $$resourceVar->getPropertyByName('statementOfScaleArchitectural')->__toString())): ?>
    <materialspec type='architectural'><?php echo esc_specialchars($value) ?></materialspec>
    <?php endif; ?>
    <?php if (0 < strlen($value = $$resourceVar->getPropertyByName('issuingJurisdictionAndDenomination')->__toString())): ?>
    <materialspec type='philatelic'><?php echo esc_specialchars($value) ?></materialspec>
    <?php endif; ?>
  </did>
