<?php

declare(strict_types=1);

namespace Sabre\CalDAV;

use Sabre\HTTP;
use Sabre\VObject;

/**
 * This unittests is created to find out why recurring events have wrong DTSTART value.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class ExpandEventsDTSTARTandDTENDbyDayTest extends \Sabre\DAVServerTest
{
    protected $setupCalDAV = true;

    protected $caldavCalendars = [
        [
            'id' => 1,
            'name' => 'Calendar',
            'principaluri' => 'principals/user1',
            'uri' => 'calendar1',
        ],
    ];

    protected $caldavCalendarObjects = [
        1 => [
           'event.ics' => [
                'calendardata' => 'BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
DTEND;TZID=Europe/Berlin:20120207T191500
RRULE:FREQ=WEEKLY;INTERVAL=1;BYDAY=TU,TH
SUMMARY:RecurringEvents on tuesday and thursday
DTSTART;TZID=Europe/Berlin:20120207T181500
END:VEVENT
END:VCALENDAR
',
            ],
        ],
    ];

    public function testExpandRecurringByDayEvent()
    {
        $request = HTTP\Sapi::createFromServerArray([
            'REQUEST_METHOD' => 'REPORT',
            'HTTP_CONTENT_TYPE' => 'application/xml',
            'REQUEST_URI' => '/calendars/user1/calendar1',
            'HTTP_DEPTH' => '1',
        ]);

        $request->setBody('<?xml version="1.0" encoding="utf-8" ?>
<C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
    <D:prop>
        <C:calendar-data>
            <C:expand start="20120210T230000Z" end="20120217T225959Z"/>
        </C:calendar-data>
        <D:getetag/>
    </D:prop>
    <C:filter>
        <C:comp-filter name="VCALENDAR">
            <C:comp-filter name="VEVENT">
                <C:time-range start="20120210T230000Z" end="20120217T225959Z"/>
            </C:comp-filter>
        </C:comp-filter>
    </C:filter>
</C:calendar-query>');

        $response = $this->request($request);

        $bodyAsString = $response->getBodyAsString();
        // Everts super awesome xml parser.
        $body = substr(
            $bodyAsString,
            $start = strpos($bodyAsString, 'BEGIN:VCALENDAR'),
            strpos($bodyAsString, 'END:VCALENDAR') - $start + 13
        );
        $body = str_replace('&#13;', '', $body);

        $vObject = VObject\Reader::read($body);

        self::assertEquals(2, count($vObject->VEVENT));

        // check if DTSTARTs and DTENDs are correct
        foreach ($vObject->VEVENT as $vevent) {
            /** @var $vevent Sabre\VObject\Component\VEvent */
            foreach ($vevent->children() as $child) {
                /** @var $child Sabre\VObject\Property */
                if ('DTSTART' == $child->name) {
                    // DTSTART has to be one of two valid values
                    self::assertContains($child->getValue(), ['20120214T171500Z', '20120216T171500Z'], 'DTSTART is not a valid value: '.$child->getValue());
                } elseif ('DTEND' == $child->name) {
                    // DTEND has to be one of two valid values
                    self::assertContains($child->getValue(), ['20120214T181500Z', '20120216T181500Z'], 'DTEND is not a valid value: '.$child->getValue());
                }
            }
        }
    }
}
