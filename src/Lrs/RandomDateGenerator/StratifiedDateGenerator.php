<?php namespace Lrs\StratifiedDateGenerator;

/**
 *	This file generates a list of stratified (random) dates.  The user supplies:
 *
 *	- Year
 *	- Maximum number of dates
 *	- Maximum results for each day of the week
 *	- Maximum results for each month of the year
 *	- Maximum results for each quarter of the year
 *
 *	NOTE: a "max" number of results is specified because the actual number of results may be less
 *	than what is requested.  For example, if a user requests 365 dates, but then excludes every 
 *	day but Saturday, obviously they aren't getting 365 dates!
 *
 *	The class creates an array of each day within the supplied year, shuffles the array, 
 *	then loops through it, picking dates that meet the user's criteria.
 */
 
class StratifiedDateGenerator {
	
	public $count;
	public $dates;
	public $default_params;
	public $maxYear = 2038;
	public $minYear = 1970;
	public $msg;
	public $params;
	public $yearRange;
	
	public function __construct($params = []) {
		$this->reset();
		$this->params($params);
	}

	public function daysOfTheWeek()  {
		return ['7' => 'Sunday',
				'1' => 'Monday',
				'2' => 'Tuesday',
				'3' => 'Wednesday',
				'4' => 'Thursday',
				'5' => 'Friday',
				'6' => 'Saturday'];
	}

	public function generate($params = false) {
		if ( $params ) {
			$this->params($params);
		}
		if ( $this->params['id'] ) {
			$this->get_existing_random_dates($this->params['id']);	
		} else {
			$this->getYearRange($this->params['year']);
			// Loop until we reach the number of requested dates
			for ( $i = 1; $i <= $this->params['num_dates']; $i++ ) {
				$break = 0; // Fail-safe
				$searching = true; // Start searching for a date
				while ( $searching && sizeof($this->yearRange) > 0 ) {
					$break++;
					if ( $break == 367 ) { // Grr, something went wrong and we looped the entire year
						break;	
					}
					// Grab a date off the front of the randomized array of dates
					$this_date = $unix = array_shift($this->yearRange);
					// We need at least quarter, day, and month to evaluate whether to proceed
					$this_date = ['quarter'	=> ceil(date('n', $this_date)/3),
								  'day'		=> date('N', $this_date),
								  'month'	=> date('n', $this_date)];
					/////
					// Note the deliberate way that each skip is checked.  Checking them 
					// in order of breadth (from wide to narrow) reduces the number of 
					// checks that might need to take place.
					/////
					// Is the limit reached on this quarter?
					$skip_quarter = $this->params['lim_quarters'] && $this->count['quarters'][$this_date['quarter']] >= $this->params['lim_quarters'];
					if ($skip_quarter ) {
						continue;	
					}
					// Is the limit reached on this month?
					$skip_month = $this->params['lim_months'] && $this->count['months'][$this_date['month']] >= $this->params['lim_months'];
					if ( $skip_month ) {
						continue;	
					}
					// Is the limit reached on this day of the week?
					$skip_day = ( $this->params['lim_days'] && $this->count['days'][$this_date['day']] >= $this->params['lim_days'] ) || ( sizeof($this->params['exclude']) > 0 && in_array($this_date['day'], $this->params['exclude']) );
					if ( $skip_day ) {
						continue;
					}
					// Increment day, month, quarter counts
					$this->count['days'][$this_date['day']]++;
					$this->count['months'][$this_date['month']]++;
					$this->count['quarters'][$this_date['quarter']]++;
					// If we made it this far we can use the date, so store a couple of extra values...
					$this_date['db'] = date('Y-m-d', $unix);
					$this_date['long'] = date('l, F jS, Y', $unix);
					$this_date['unix'] = $unix;
					// ...record the date...
					$this->dates[$this_date['db']] = $this_date;
					// ...and end the loop
					$searching = false;
				}
			}
			// Sort by year, month, day
		}
		ksort($this->dates);
		return $this->dates;
	}

	public function getYearRange($year) {
		if ( $year || sizeof($this->yearRange) == 0 ) {
			$current = strtotime($year.'-01-01');
			$end = strtotime($year.'-12-31');
			while ( $current <= $end ) {
				$this->yearRange[] = $current;
				$current = strtotime('+1 day', $current);
			}
		}
		shuffle($this->yearRange);
		return $this->yearRange;
	}
	
	public function getMessages() {
		if ( $this->hasMessages() ) {
			return $this->msg;	
		}
		return false;
	}
	
	public function hasMessages() {
		return sizeof($this->msg) > 0;	
	}
	
	public function params($params) {
		if ( !is_array($params) ) {
			parse_str($params, $params);
		}
		$this->params = array_merge($this->default_params, $params);
		// Limit to UNIX years
		if ($this->params['year'] <  $this->minYear || $this->params['year'] >  $this->maxYear ) {
			$this->params['year'] = date('Y', time());
		}
		// Default to 24 results
		if ( $this->params['num_dates'] < 1 ) {
			$this->params['num_dates'] = 24;
		}
		// Only one year can be suplied, so limit to 366 days (can't forget leap years!)
		if ( $this->params['num_dates'] > 366 ) {
			$this->params['num_dates'] = 366;
		}
		// Can't exclude every day of the week
		if ( sizeof($this->params['exclude']) == 7 ) {
			$this->msg[] = 'Oops!  You can\'t exclude <strong>every</strong> day of the week or there is nothing to display!';	
		}
		// Validate days
		if (  $this->params['lim_days'] && ( $this->params['num_dates'] / $this->params['lim_days'] > 7 ) ) {
			$cond = '';
			$mult = 7;
			$max = $this->params['lim_days'] * $mult;
			// Account for individual days of the week that are excluded
			if ( sizeof($this->params['exclude']) > 0 ) {
				$cond = ' (and excluded '.sizeof($this->params['exclude']).' other days)';
				$max = $mult - sizeof($this->params['exclude']);	
			}
			$this->msg[$max] = 'Oops!  Since you limited the results to '.$this->params['lim_days'].' maximum day(s) for each day of the week'.$cond.', only '.$max.' date(s) can be displayed.';
		}
		// Validate months
		if (  $this->params['lim_months'] && ( $this->params['num_dates'] / $this->params['lim_months'] > 12 ) ) {
			$max = $this->params['lim_months'] * 12;
			$this->msg[$max] = 'Oops!  Since you limited the results to '.$this->params['lim_months'].' maximum month(s) for each month of the year, only '.$max.' dates can be displayed.';
		}
		// Validate quarters
		if (  $this->params['lim_quarters'] && ( $this->params['num_dates'] / $this->params['lim_quarters'] > 4 ) ) {
			$max = $this->params['lim_quarters'] * 4;
			$this->msg[$max] = 'Oops!  Since you limited the results to '.$this->params['lim_quarters'].' maximum quarter(s) for each quarter of the year, only '.$max.' dates can be displayed.';
		}
		return $this;	
	}

	public function reset() {
		$this->count = ['days' 		=> array_fill_keys(array_keys($this->daysOfTheWeek()), 0),
						'months'	=> array_fill_keys(range(1, 12), 0),
						'quarters'	=> array_fill_keys(range(1,4), 0)];
		$this->dates = [];
		$this->default_params = ['id'			=> false,
								 'year' 		=> date('Y', time()),
								 'num_dates'	=> 24,
								 'exclude'		=> [],
								 'lim_days'		=> false,
								 'lim_months'	=> false,
								 'lim_quarters'	=> false];
		$this->msg = [];
		$this->yearRange = [];
		return $this;
	}
	
}
