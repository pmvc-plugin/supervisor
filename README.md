[![Latest Stable Version](https://poser.pugx.org/pmvc-plugin/supervisor/v/stable)](https://packagist.org/packages/pmvc-plugin/supervisor) 
[![Latest Unstable Version](https://poser.pugx.org/pmvc-plugin/supervisor/v/unstable)](https://packagist.org/packages/pmvc-plugin/supervisor) 
[![Build Status](https://travis-ci.org/pmvc-plugin/supervisor.svg?branch=master)](https://travis-ci.org/pmvc-plugin/supervisor)
[![License](https://poser.pugx.org/pmvc-plugin/supervisor/license)](https://packagist.org/packages/pmvc-plugin/supervisor)
[![Total Downloads](https://poser.pugx.org/pmvc-plugin/supervisor/downloads)](https://packagist.org/packages/pmvc-plugin/supervisor) 

Supervisor
===============

## Daemon
if run with Daemon mode and code not exit with 1,
Supervisor will auto restore Daemon, this will not effect script mode





## Install with Composer
### 1. Download composer
   * mkdir test_folder
   * curl -sS https://getcomposer.org/installer | php

### 2. Install by composer.json or use command-line directly
#### 2.1 Install by composer.json
   * vim composer.json
```
{
    "require": {
        "pmvc-plugin/supervisor": "dev-master"
    }
}
```
   * php composer.phar install

#### 2.2 Or use composer command-line
   * php composer.phar require pmvc-plugin/supervisor

