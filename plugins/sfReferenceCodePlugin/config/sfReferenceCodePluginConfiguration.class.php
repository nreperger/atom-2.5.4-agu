<?php

class sfReferenceCodePluginConfiguration extends sfPluginConfiguration
{
  public static
    $summary = 'Add separator to Country Code & Identifier Area.',
    $version = '1.0.0';

  // /**
  //  * @see sfPluginConfiguration
  //  */
  public function initialize()
  {
    $enabledModules = sfConfig::get('sf_enabled_modules');
    $enabledModules[] = 'sfReferenceCodePlugin';
    sfConfig::set('sf_enabled_modules', $enabledModules);

  }
}