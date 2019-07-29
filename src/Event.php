<?php
namespace Akkroo;

/**
 * Represents an Event resource
 *
 * @property int id  Event ID
 */
class Event extends Resource
{
    /**
     * Data Capture
     * @const
     */
    const TYPE_DATA_CAPTURE = 1;

    /**
     * Check-in (no Registration)
     * @const
     */
    const TYPE_CHECKIN = 2;

    /**
     * Check-in or Register
     * @const
     */
    const TYPE_CHECKIN_OR_REGISTER = 3;

    /**
     * Check-in then Data Capture (no Registration)
     * @const
     */
    const TYPE_CHECKIN_THEN_DATA_CAPTURE = 4;

    /**
     * Check-in or Register then Data Capture
     * @const
     */
    const TYPE_CHECKIN_OR_REGISTER_THEN_DATA_CAPTURE = 5;
}
