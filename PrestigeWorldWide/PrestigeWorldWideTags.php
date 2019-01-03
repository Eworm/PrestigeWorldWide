<?php

namespace Statamic\Addons\PrestigeWorldWide;

use Recurr\Rule;
use Recurr\Transformer;
use Spatie\CalendarLinks\Link;
use Statamic\Contracts\Forms\Submission;
use Statamic\API\Form;
use Statamic\API\Entry;
use Statamic\Extend\Collection;
use Statamic\Extend\Tags;
use Statamic\API\Folder;
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
     * The {{ prestige_world_wide:start_date }} tag
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
     * The {{ prestige_world_wide:end_date }} tag
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
     * The {{ prestige_world_wide:recurring }} tag
     *
     * @return array
     */
    public function recurring()
    {

        if ($this->context['pw_recurring'] == true) {

            $timezone  = $this->context['settings']['system']['timezone'];
            $startdate = new \DateTime($this->startDate());
            $enddate = new \DateTime($this->endDate());

            if (isset($this->context['pw_recurring_ends'])) {
                $ends = $this->context['pw_recurring_ends'];
            }
            if (isset($this->context['pw_recurring_frequency'])) {
                $frequency = $this->context['pw_recurring_frequency'];
            }
            if (isset($this->context['pw_recurring_count'])) {
                $count = $this->context['pw_recurring_count'];
            }
            if (isset($this->context['pw_recurring_until'])) {
                $until = new \DateTime($this->context['pw_recurring_until']);
            }
            if (isset($this->context['pw_recurring_interval'])) {
                $interval = $this->context['pw_recurring_interval'];
            }

            $transformer = new Transformer\ArrayTransformer();

            if ($ends == 'after') {

                $rule = (new \Recurr\Rule)
                    ->setStartDate($startdate)
                    ->setEndDate($enddate)
                    ->setTimezone($timezone)
                    ->setCount($count)
                    ->setInterval($interval)
                    ->setFreq($frequency);

            } else {

                $rule = (new \Recurr\Rule)
                    ->setStartDate($startdate)
                    ->setEndDate($enddate)
                    ->setTimezone($timezone)
                    ->setFreq($frequency)
                    ->setInterval($interval)
                    ->setUntil($until);

            }

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
     * The {{ prestige_world_wide:google_calendar }} tag
     *
     * @return string
     */
    private function calendarLink($type)
    {
        $from = \DateTime::createFromFormat('Y-m-d H:i', $this->startDate());
        $to = \DateTime::createFromFormat('Y-m-d H:i', $this->endDate());

        $link = Link::create(urlencode($this->context['title']), $from, $to)
            ->description('')
            ->address($this->context['pw_location']);

        if ($type == 'ics') {
            return $link->ics();
        } elseif ($type == 'google') {
            return $link->google();
        }
    }

    /**
     * Remove certain chars from a date to make a RFC339 datetime
     *
     * @return string
     */
    private function rfc3339($date)
    {
        $chars = array('-', ':');
        $rfc3339 = str_replace($chars, '', $date);
        $rfc3339 = substr($rfc3339, 0, strpos($rfc3339, '+'));
        return $rfc3339;
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
        $substorage     = Folder::getFilesByType('/site/storage/forms/' . $formname, 'yaml');
        $c              = 0;

        foreach ($substorage as $sub) {
            $file = File::get($sub);
            $yaml = Yaml::parse($file);

            if ($yaml['pw_id'] == $entry_id) {
                $c++;
            }
        }
        return $c;
    }

}