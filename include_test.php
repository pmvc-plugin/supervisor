<?php
$path = __DIR__.'/vendor/autoload.php';
require $path;
\PMVC\Load::plug();
\PMVC\addPlugInFolders(['../']);

if (!class_exists('PHPUnit_Framework_TestCase')) {
  class PHPUnit_Framework_TestCase extends
    \PHPUnit\Framework\TestCase
  {
  }
}

class PMVC_TestCase extends PHPUnit_Framework_TestCase
{
  public function haveString($needle, $haystack) {
    if (is_callable([$this, "assertStringContainsString"])) {
      $this->assertStringContainsString($needle, $haystack);
    } else {
      $this->assertContains($needle, $haystack);
    }
  }
}
