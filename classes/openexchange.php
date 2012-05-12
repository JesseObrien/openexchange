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
	private static $_rates = array();

	/**
	 * Return the license from openexchangerates.org/latest.json
	 * @return string
	 */
	public static function license()
	{
		return static::$_license;
	}

	/**
	 * Return the dislaimer from openexchangerates.org/latest.json
	 * @return string
	 */
	public static function disclaimer()
	{
		return static::$_disclaimer;
	}

	/**
	 * Return the base currency from openexchangerates.org/latest.json
	 * @return string
	 */
	public static function base_currency()
	{
		return static::$_base;
	}

	/**
	 * Alias for the base_currency method
	 * @return string
	 */
	public static function base()
	{
		return static::base_currency();
	}

	/**
	 * Return the timestamp from openexchangerates.org/latest.json
	 * *Note this function uses date formats from php to get the timestamp back
	 * @return int unix_timestamp
	 */
	public static function timestamp( $format = null )
	{
		if( ! empty($format))
		{
			return date($format, static::$_timestamp);
		}
		return static::$_timestamp;
	}

	/**
	 * Return the rates from openexchangerates.org/latest.json
	 * @return array
	 */
	public static function rates()
	{
		return static::$_rates;
	}

	/**
	 * Fetch the current exchange rates from the server
	 * @param boolean Ignore the cache on this fetch, essentially force update from server
	 * @return void
	 *
	 * @todo Probably should try/catch this in case a network connection goes down or times out, then it can fail gracefully
	 */
	public static function fetch( $ignore_cache = false )
	{
		$latest_scrape = null;

		if( ! $ignore_cache )
		{
			$latest_scrape = \Cache::get('open_exchange_latest');
		}

		if(empty($latest_scrape))
		{
			$latest_scrape = json_decode(file_get_contents('http://openexchangerates.org/latest.json') );

			// Cache is set to expire on the next hourly update of openexchangerates.org
			$difference = intval(abs(($latest_scrape->timestamp - time()) / 60));

			// If the openxchangerates site fails, fall back to github!
			if( $difference > 60 )
			{
				$latest_scrape = json_decode(file_get_contents('https://raw.github.com/currencybot/open-exchange-rates/master/latest.json'));
				$difference = intval(abs(($latest_scrape->timestamp - time()) / 60));
			}

			// If the expiry is negative, set the expiry to check in another 15 mins
			// If not, set it to 60 mins - the difference of now and the timestamp
			$expires = (60 - $difference) > -1 ? (60 - $difference) : 15;
			\Cache::put('open_exchange_latest', $latest_scrape, $expires);
		}
		
		static::$_disclaimer = $latest_scrape->disclaimer;
		static::$_license = $latest_scrape->license;
		static::$_timestamp = $latest_scrape->timestamp;
		static::$_base = $latest_scrape->base;
		static::$_rates = $latest_scrape->rates;

		return $latest_scrape;
	}

	/**
	 * Convert two currencies to one another
	 * @param float amount to convert
	 * @param string currency from (list found in rates)
	 * @param string currency to (list found in rates)
	 */
	public static function convert( $from, $to = null, $amount = null )
	{
		if(empty($to))
		{
			$to = static::$_base;
		}

		$rate = static::$_rates->{$from} * (1 / static::$_rates->{$to});

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

			return round( ($amount * $rate) / $divisor, 4);
		}
		else
		{
			return $rate;
		}


	}
}