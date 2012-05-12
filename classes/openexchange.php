<?php

/**
 * OpenExchange, Exchange rate bundle for Laravel PHP Framework
 * @author Jesse O'Brien <jesse@jesse-obrien.ca>
 * @copyright 2012 Jesse O'Brien <jesse@jesse-obrien.ca>
 * @license MIT License <http://www.opensource.org/licenses/mit>
 * @license License for rates lives in OpenExchange::license() or at http://www.openexchangerates.org
 */
class OpenExchange
{
	private static $_disclaimer = "";
	private static $_license = "";
	private static $_timestamp = 0;
	private static $_base = "";
	private static $_currencies = array();
	private static $_rates = array();

	/**
	 * Initializer method
	 * @return void
	 */
	public static function init()
	{
		static::fetch();
	}

	/**
	 *
	 */
	private static function _is_expired()
	{
		$difference = intval(abs((static::$_timestamp - time()) / 60));
		if( $difference > 60)
		{
			static::fetch();
		}
	}

	/**
	 * Return the license from openexchangerates.org/latest.json
	 * @return string
	 */
	public static function license()
	{
		static::_is_expired();
		return static::$_license;
	}

	/**
	 * Return the dislaimer from openexchangerates.org/latest.json
	 * @return string
	 */
	public static function disclaimer()
	{
		static::_is_expired();
		return static::$_disclaimer;
	}

	/**
	 * Get the list of available currencies from openexchangerates.org/currencies.json
	 * @return object
	 */
	public static function currencies()
	{
		static::_is_expired();
		return static::$_currencies;
	}

	/**
	 * Return the base currency from openexchangerates.org/latest.json
	 * @param string The new base currency for this execution
	 * @return string The base currency for converting
	 */
	public static function base_currency( $new_base = null )
	{
		static::_is_expired();
		if( ! empty($new_base) && is_string($new_base) )
		{
			// If the currency doesn't exist in the $_rates array, we can't use it
			if(static::$_rates->{$new_base})
			{
				static::$_base = $new_base;
			}
			else
			{
				throw new Exception("Invalid currency to set base.");
			}
		}

		return static::$_base;
	}

	/**
	 * Alias for the base_currency method
	 * @param string The new base currency for this execution
	 * @return string The base currency for converting
	 */
	public static function base( $new_base = null)
	{
		static::_is_expired();
		return static::base_currency( $new_base );
	}

	/**
	 * Return the timestamp from openexchangerates.org/latest.json
	 * *Note this function uses date formats from php to get the timestamp back
	 * *eg: The pattern 'G:i:s - F j, Y' will produce 15:00:00 - May 12, 2012
	 * @return int unix_timestamp
	 */
	public static function timestamp( $format = null )
	{
		static::_is_expired();
		if( ! empty($format))
		{
			return date($format, static::$_timestamp);
		}
		return static::$_timestamp;
	}

	/**
	 * Return the rates from openexchangerates.org/latest.json
	 * @return object 
	 */
	public static function rates()
	{
		static::_is_expired();
		return static::$_rates;
	}

	/**
	 * Fetch the current exchange rates from the server
	 * @param boolean Ignore the cache on this fetch, essentially force update from server
	 * @return bool
	 *
	 * @todo Probably should try/catch this in case a network connection goes down or times out, then it can fail gracefully
	 */
	public static function fetch( $ignore_cache = false )
	{
		$latest_scrape = null;
		$currencies = null;
		if( ! $ignore_cache )
		{
			$latest_scrape = \Cache::get('open_exchange_latest');
		}

		if(empty($latest_scrape))
		{
			$latest_scrape = json_decode(file_get_contents('http://openexchangerates.org/latest.json') );

			// If this fails we might be timing out
			if( ! $latest_scrape )
			{
				$difference = 61;
			}
			else
			{
				// Cache is set to expire on the next hourly update of openexchangerates.org
				$difference = intval(abs(($latest_scrape->timestamp - time()) / 60));
			}

			// If the openxchangerates site fails, fall back to github!
			if( $difference > 60 )
			{
				$latest_scrape = json_decode(file_get_contents('https://raw.github.com/currencybot/open-exchange-rates/master/latest.json'));

				// If this fails then we're really in trouble, likely no internet connection
				if( ! $latest_scrape )
				{
					// Unsure what to do here...
				}
				else
				{
					$difference = intval(abs(($latest_scrape->timestamp - time()) / 60));
				}
			}

			// If the expiry is negative, set the expiry to check in another 15 mins
			// If not, set it to 60 mins - the difference of now and the timestamp
			$expires = (60 - $difference) > -1 ? (60 - $difference) : 15;
			\Cache::put('open_exchange_latest', $latest_scrape, $expires);
		}

		// Fetch the available currencies, have them expire once every 3 hours
		$currencies = \Cache::get('open_exchange_currencies');
		if(empty($currencies))
		{
			$currencies = json_decode(file_get_contents('http://openexchangerates.org/currencies.json'));
			\Cache::put('open_exchange_currencies', $currencies, 180);
		}

		static::$_disclaimer = $latest_scrape->disclaimer;
		static::$_license = $latest_scrape->license;
		static::$_timestamp = $latest_scrape->timestamp;
		static::$_base = $latest_scrape->base;
		static::$_rates = $latest_scrape->rates;
		static::$_currencies = $currencies;

		return true;
	}

	/**
	 * Convert two currencies to one another
	 * @param float amount to convert
	 * @param string currency from - 3 letter abbreviation (list found in OpenExchange::rates)
	 * @param string currency to - 3 letter abbreviation (list found in OpenExchange::rates)
	 */
	public static function convert( $from, $to = null, $amount = null )
	{
		static::_is_expired();
		if(empty($to))
		{
			$to = static::$_base;
		}

		$rate = static::$_rates->{$to} * (static::$_rates->{static::$_base} / static::$_rates->{$from});

		if( ! empty($amount))
		{
			// absolute value of amount, no negatives!
			$amount = abs($amount);

			// convert amount to cents for more accuracy
			if( $amount < 1)
			{
				$divisor = 1;	
			}
			else if( $amount >= 1 && $amount < 100)
			{
				$divisor = 100;
				$amount = $amount * 100;
			}
			else if( $amount >= 100 && $amount < 1000 )
			{
				$divisor = 1000;
				$amount = $amount * 1000;
			}
			else if($amount >= 1000 && $amount < 1000000)
			{
				$divisor = 100000;
				$amount = $amount * 1000000;
			}
			else if( $amount >= 1000000 && $amount < 100000000)
			{
				$divisor = 100000000;
				$amount = $amount * 1000000000;
			}
			else if( $amount >= 1000000000 && $amount < 1000000000000)
			{
				$divisor = 1000000000000;
				$amount = $amount * 1000000000000;
			}

			return ($amount * $rate) / $divisor;
		}
		else
		{
			return $rate;
		}
	}
}
OpenExchange::init();