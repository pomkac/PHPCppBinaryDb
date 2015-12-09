#Binary database php extension

### Database creator

* Written on PHP
* Execute through 
```
php collector.php  --configFile=<root_to_opts.json>
```
* Tests in t folder (phpunit)
* Run tests through
```
phpunit --configuration phpunit.xml --debug
```

### PHP version of reader

* Located in DbCarrier.php
* Interface
```
interface CarrierDb {
  /**
    * @param string $dbPath 
   **/
  public function __construct($dbPath); 

  /**
   * @return array версия структуры, номер сборки, дата сборки, количество интервалов
   **/
  public function getDbInfo();

  /**
   * @param string $ip
   * @return string
   **/
  public function get($ip);
}
```

### C++ version of reader (PHP extension)

* Located in extension folder
* Requires Boost library
* Under Windows can be built with Visual Studio 2012 or higher
* Under Linux/Mac OS X built by gcc
```
phpize
./configure
make
```