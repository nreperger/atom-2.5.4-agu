<?php

class ReplaceQubitInformationObjectListener
{
    /**
     * Este método se ejecutará para reemplazar la clase QubitInformationObject por la extendida.
     */
    public function execute()
    {
        // Reemplazamos QubitInformationObject por ExtendedQubitInformationObject en el autoload
        sfContext::getInstance()->getAutoload()->addNamespace('QubitInformationObject', 'plugins/sfReferenceCodePlugin/lib/model');
    }
}