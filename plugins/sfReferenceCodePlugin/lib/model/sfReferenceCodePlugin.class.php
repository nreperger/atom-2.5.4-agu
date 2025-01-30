<?php

/**
 * sfReferenceCodePlugin add the separator character into the reference code
 *
 *
 * @package    sfReferenceCodePlugin
 * @author     Mariano Reperger <mariano@reperger.com>
 */
class sfReferenceCodePluginObject extends QubitInformationObject
{
    /**
     * Sobreescribe el método para cambiar la lógica de referencia.
     *
     * @param bool $includeRepoAndCountry
     * @return string
     */
    public function getInheritedReferenceCode($includeRepoAndCountry = true)
    {
        error_log('CustomQubitInformationObject cargado correctamente', 0);
        var_dump("HERE");die;
        if (!isset($this->identifier)) {
            return;
        }

        $identifier = array();
        $repository = null;
        $item = $this;

        // Ascendemos por la jerarquía para construir el identificador heredado manualmente
        while ($item && $item->id != QubitInformationObject::ROOT_ID) {
            if (isset($item->identifier)) {
                array_unshift($identifier, $item->identifier);
            }

            if (isset($item->repository)) {
                $repository = $item->repository;
            }

            $item = $item->parent;
        }

        $separator = sfConfig::get('app_separator_character', '-');
        $identifier = implode($separator, $identifier);

        if ($includeRepoAndCountry)
        {
            if (isset($repository->identifier))
            {
                $identifier = "$repository->identifier" . $separator . "$identifier";
            }

            if (isset($repository))
            {
                $countryCode = $repository->getCountryCode();

                if (isset($countryCode))
                {
                $identifier = "$countryCode" . $separator . "$identifier";
                }
            }
        }

        return $identifier;
    }
}