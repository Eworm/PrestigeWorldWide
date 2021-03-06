<?php

namespace Statamic\Addons\PrestigeWorldWide;

use Statamic\Addons\PrestigeWorldWide\iCal;
use Recurr\Rule;
use Recurr\Transformer;
use Spatie\CalendarLinks\Link;
use Statamic\Contracts\Forms\Submission;
use Statamic\API\Form;
use Statamic\API\Entry;
use Statamic\Extend\Collection;
use Statamic\Extend\Tags;
use Statamic\API\Folder;
use Statamic\API\Config;
use Illuminate\Support\Facades\Storage;
use Statamic\Data\DataCollection;
use Statamic\API\File;
use Statamic\API\Yaml;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PrestigeWorldWideTags extends Tags
{
    /**
     * The {{ prestige_world_wide }} tag
     *
     * @return string|array
     */
    public function index()
    {
        //
    }

    /**
     * Get the start date
     *
     * @return string
     */
    private function startDate()
    {
        if (isset($this->context['pw_start_date'])) {
            return $this->context['pw_start_date'];
        } else  {
            return NULL;
        }
    }

    /**
     * Get the end date
     *
     * @return string
     */
    private function endDate()
    {
        if (isset($this->context['pw_end_date'])) {
            return $this->context['pw_end_date'];
        } else  {
            return NULL;
        }
    }

    /**
     * Get the description
     *
     * @return string
     */
    private function description()
    {
        if (isset($this->context['pw_description'])) {
            return $this->context['pw_description'];
        } else  {
            return NULL;
        }
    }

    /**
     * Get the location
     *
     * @return string
     */
    private function location()
    {
        if (isset($this->context['pw_location'])) {
            return $this->context['pw_location'];
        } else  {
            return NULL;
        }
    }

    /**
     * Get the location name
     *
     * @return string
     */
    private function locationName()
    {
        if (isset($this->context['pw_location_name'])) {
            return $this->context['pw_location_name'];
        } else  {
            return NULL;
        }
    }

    /**
     * Get the city
     *
     * @return string
     */
    private function city()
    {
        if (isset($this->context['pw_city'])) {
            return $this->context['pw_city'];
        } else  {
            return NULL;
        }
    }

    /**
     * Get the postal code
     *
     * @return string
     */
    private function postal()
    {
        if (isset($this->context['pw_postal'])) {
            return $this->context['pw_postal'];
        } else  {
            return NULL;
        }
    }

    /**
     * Get the country
     *
     * @return string
     */
    private function country()
    {
        if (isset($this->context['pw_country'])) {
            return $this->context['pw_country'];
        } else  {
            return NULL;
        }
    }

    /**
     * The {{ prestige_world_wide:recurring }} tag
     *
     * @return array
     */
    public function recurring()
    {

        if ($this->context['pw_recurring'] == true) {

            if ($this->context['pw_recurring_frequency'] != 'CUSTOM') {

                $timezone  = $this->context['settings']['system']['timezone'];
                $startdate = new \DateTime($this->startDate());
                $enddate = new \DateTime($this->endDate());
                $newrule = '';

                if (isset($this->context['pw_recurring_ends'])) {
                    $ends = $this->context['pw_recurring_ends'];
                }
                if (isset($this->context['pw_recurring_frequency'])) {
                    $frequency = $this->context['pw_recurring_frequency'];
                    $newrule .= 'FREQ=' . $frequency;
                }
                if (isset($this->context['pw_recurring_byday'])) {
                    $byday = $this->context['pw_recurring_byday'];
                    $newrule .= ';BYDAY=' . \implode(',',$byday);
                }
                if (isset($this->context['pw_recurring_count'])) {
                    $count = $this->context['pw_recurring_count'];
                    $newrule .= ';COUNT=' . $count;
                }
                if (isset($this->context['pw_recurring_until'])) {
                    $until = new \DateTime($this->context['pw_recurring_until']);
                    $newrule .= ';UNTIL=' . $until->format('Y-m-d');
                }
                if (isset($this->context['pw_recurring_interval'])) {
                    $interval = $this->context['pw_recurring_interval'];
                    $newrule .= ';INTERVAL=' . $interval;
                }
                $transformer = new Transformer\ArrayTransformer();
                $rule = new \Recurr\Rule($newrule, $startdate, $enddate, $timezone);

                $ruledates = $transformer->transform($rule);
                $dates = [];

                foreach ($ruledates as $date) {

                    $startdate = $date->getStart();
                    $carbon_start = $this->dtCarbon($startdate);
                    $enddate = $date->getEnd();
                    $carbon_end = $this->dtCarbon($enddate);

                    $item = array(
                        'start' => $carbon_start->toDateTimeString(),
                        'end' => $carbon_end->toDateTimeString()
                    );
                    $dates[] = $item;

                }
                return $this->parseLoop($dates);

            } else {

                $customdates = $this->context['pw_recurring_manual'];
                $dates = [];

                // Add the start & end date of the current event
                $dates[] = array(
                    'start' => $this->startDate(),
                    'end' => $this->endDate()
                );

                foreach ($customdates as $date) {

                    $startdate = $date['pw_recurring_manual_start'];
                    $enddate = $date['pw_recurring_manual_end'];

                    $item = array(
                        'start' => $startdate,
                        'end' => $enddate
                    );
                    $dates[] = $item;

                }
                return $this->parseLoop($dates);

            }
        }
    }

    /**
     * The {{ prestige_world_wide:participants }} tag
     *
     * @return string
     */
    public function participants()
    {
        if (isset($this->context['pw_form'])) {

            $entry_id       = $this->context['id'];
            $pw_formname    = $this->context['pw_form'];
            return $this->submissions($pw_formname, $entry_id);

        }
    }

    /**
     * The {{ prestige_world_wide:icalendar }} tag
     *
     * @return string
     */
    public function icalendar()
    {
        return $this->calendarLink('ics');
    }

    /**
     * The {{ prestige_world_wide:google_calendar }} tag
     *
     * @return string
     */
    public function googleCalendar()
    {
        return $this->calendarLink('google');
    }

    /**
     * Return a link
     *
     * @return string
     */
    private function calendarLink($type)
    {
        // Format the start & end dates
        $from = \DateTime::createFromFormat('Y-m-d H:i', $this->startDate(), $this->getTimezone());
        $to = \DateTime::createFromFormat('Y-m-d H:i', $this->endDate(), $this->getTimezone());

        // Get the description
        $description = isset($this->context['pw_description']) ? $this->context['pw_description'] : "";

        // Add an optional location
        if (isset($this->context['pw_location'])) {
            $link = Link::create(urlencode($this->context['title']), $from, $to)
            ->description($description)
            ->address($this->context['pw_location']);
        } else {
            $link = Link::create(urlencode($this->context['title']), $from, $to)
            ->description($description);
        }

        if ($type == 'ics') {
            return $link->ics();
        } elseif ($type == 'google') {
            return $link->google();
        }
    }

    /**
     * Transform a datetime object to Carbon
     *
     * @return string
     */
    private function dtCarbon($date)
    {
        return Carbon::instance($date);
    }

    /**
     * The {{ prestige_world_wide:has_form }} tag
     *
     * @return true/false
     */
    public function hasForm()
    {
        if (isset($this->context['pw_has_form']) && isset($this->context['pw_form'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * The {{ prestige_world_wide:max_participants }} tag
     *
     * @return string
     */
    private function maxParticipants()
    {
        if (isset($this->context['pw_max_participants'])) {
            return $this->context['pw_max_participants'];
        }
    }

    /**
     * The {{ prestige_world_wide:is_full }} tag
     *
     * @return true/false
     */
    public function isFull()
    {
        if ($this->hasForm()) {

            $entry_id       = $this->context['id'];
            $pw_formname    = $this->context['pw_form'];
            $pw_form        = Form::all();
            $pw_submissions = $this->submissions($pw_formname, $entry_id);
            $pw_max         = $this->maxParticipants();

            foreach ($pw_form as $pw_form) {

                if ($pw_form['name'] == $pw_formname) {

                    if ($pw_max !== NULL) {

                        if ($pw_submissions >= $pw_max) {
                            return true;
                        } else {
                            return false;
                        }
                    }
                }
            }

        } else {
            return false;
        }
    }

    /**
     * Return the number of submissions for a form connected to an entry
     *
     * @return mixed
     */
    private function submissions($formname, $entry_id)
    {
        // $substorage = Folder::getFilesByType('/site/storage/forms/' . $formname, 'yaml');
        // $c = 0;
        //
        // foreach ($substorage as $sub) {
        //     $file = File::get($sub);
        //     $yaml = Yaml::parse($file);
        //
        //     if ($yaml['pw_id'] == $entry_id) {
        //         $c++;
        //     }
        // }
        // return $c;
    }

    /**
     * The {{ prestige_world_wide:calendar }} tag
     *
     * @return string
     */
    public function calendar()
    {
        $start = $this->getParam('start');
        $end = $this->getParam('end');
        if (isset($end))
        {
            $end = carbon($start)->modify($end)->format('Y-m-d H:i');
        }
        $data = [];
        $ical = new iCal();
        $ical = $this->getFromCache($ical, 'pw_ical');
        $data = $this->getEvents($ical, $data, $start, $end);
        usort($data, array($this, 'dateSort'));
        return $this->parseLoop($data);
    }

    /**
     * Get all events
     *
     * @return array
     */
    private function getEvents($ical, $data, $start, $end)
    {
        if (isset($start) && isset($end))
        {
            $events = $ical->eventsByDateBetween($start, $end);
        }
        else
        {
            $events = $ical->eventsByDate();
        }

        foreach ($events as $date => $days)
        {
            foreach ($days as $event)
            {
                $data[] = $this->addEventData($event);
            }
        }
        return $data;
    }

    /**
     * Add event data
     *
     * @return array
     */
    private function addEventData($event)
    {
        return [
            'categories' => $event['event']->categories(),
            'duration' => $event['event']->duration(),
            'end_time' => $event['event']->timeEnd(),
            'location' => $event['event']->location,
            'start_date' => $event['date'],
            'start_time' => $event['event']->timeStart(),
            'status' => $event['event']->status,
            'title' => $event['event']->title(),
            'url' => $event['event']->url()
        ];
    }

    /**
     * Sort events
     *
     * @return array
     */
    private static function dateSort($a, $b)
    {
        if ($a['start_date'] == $b['start_date'])
        {
            return 0;
        }
        return ($a['start_date'] < $b['start_date']) ? -1 : 1;
    }

    /**
     * Get a file from cache
     *
     * @return yaml
     */
    private function getFromCache($ical, $title)
    {
        return $ical->cache($this->cache->get($title));
    }

    /**
     * The {{ prestige_world_wide:schema }} tag
     *
     * @return string
     */
    public function schema()
    {
        $startdate = \DateTime::createFromFormat('Y-m-d H:i', $this->startDate(), $this->getTimezone());
        $enddate = \DateTime::createFromFormat('Y-m-d H:i', $this->endDate(), $this->getTimezone());

        return '<script type="application/ld+json">
                {
                    "@context": "https://schema.org",
                    "@type": "Event",
                    "name": "' . $this->context['title'] . '",
                    "description": "' . $this->description() . '",
                    "startDate": "' . $startdate->format('Y-m-d H:i T') . '",
                    "endDate": "' . $enddate->format('Y-m-d H:i T') . '",
                    "location": {
                        "@type": "Place",
                        "name": "' . $this->locationName() . '",
                        "address": {
                            "@type": "PostalAddress",
                            "streetAddress": "' . $this->location() . '",
                            "addressLocality": "' . $this->city() . '",
                            "postalCode": "' . $this->postal() . '",
                            "addressCountry": "' . $this->country() . '"
                        }
                    }
                }
                </script>';
    }


    /**
     * Get the timezone
     *
     * @return string
     */
    private function getTimezone()
    {
        // Use the system or the event timezone
        if ($this->getConfig('event_timezone') == false) {
            return new \DateTimeZone(Config::get('system.timezone'));
        } else {
            return new \DateTimeZone($this->context['pw_timezone']);
        }
    }
}
