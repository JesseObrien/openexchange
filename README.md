#OpenExchange

Exchange rate bundle built around http://www.openexchangerates.org

##Installation

Install using artisan for Laravel : 
	
	php artisan bundle:install openexchange

Add openexchange to `applications/bundles.php` by enabling auto start :

	return array('openexchange' => array('auto' => true));

Start using openexchange via the methods outlined below!

##Useage

Basic :
	
**Note: I'm purposefully not rounding numbers to retain accuracy. You can do what you wish with them.** 

```php
<?php
	echo OpenExchange::convert('GBP'); // Default converts currency to OpenExchange::base();
	0.622336
	
	echo OpenExchange::convert('AUD', 'CAD');
	0.99662440934656

	echo OpenExchange::convert('AUD', 'CAD', 520);
	518.24469286021
	
	echo "Rates as of: ".OpenExchange::timestamp('G:i:s - F j, Y');
	Rates as of: 15:00:53 - May 12, 2012
	
```

Advanced :

```php
<?php
	// Get the base currency
	echo OpenExchange::base();
	USD
	
	// Alias
	echo OpenExchange::base_currency();
	USD
	
	// Set a new base currency
	echo Openexchange::base('GBP');
	GBP
	
	// Show the license for openexchangerates.org
	echo OpenExchange::license();
	Data collected from various providers with public-facing APIs; copyright may apply; not for resale; no warranties given. Full license info: http://openexchangerates.org/license/
	
	// Show the disclaimer for openexchangerates.org
	echo OpenExchange::disclaimer();
	This data is collected from various providers and provided free of charge for informational purposes only, with no guarantee whatsoever of accuracy, validity, availability, or fitness for any purpose; use at your own risk. Other than that, have fun! More info: http://openexchangerates.org/terms/
	
	// Get the rates object
	$rates = OpenExchange::rates();
	echo $rates->USD;
	1
```
	