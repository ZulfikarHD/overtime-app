<?php

namespace App\Enums;

/**
 * Likely causes the Early Warning follow-up generator can detect (E10-08).
 *
 * The case order is the catalog order and breaks ties when two causes score the same.
 */
enum AlertCause: string
{
    case ManpowerShortage = 'MANPOWER_SHORTAGE';
    case CategoryDominant = 'CATEGORY_DOMINANT';
    case EmployeeConcentration = 'EMPLOYEE_CONCENTRATION';
    case HolidayOvertime = 'HOLIDAY_OVERTIME';
    case UnplannedOvertime = 'UNPLANNED_OVERTIME';
    case Unknown = 'UNKNOWN';

    /**
     * Label shown in Administration (translated through `__()`).
     */
    public function label(): string
    {
        return match ($this) {
            self::ManpowerShortage => 'Manpower shortage',
            self::CategoryDominant => 'One dominant overtime category',
            self::EmployeeConcentration => 'Overtime concentrated on a few employees',
            self::HolidayOvertime => 'Overtime on weekends and holidays',
            self::UnplannedOvertime => 'Overtime above the approved plan',
            self::Unknown => 'Cause not identified',
        };
    }

    /**
     * One-line description of the detector, shown next to the PIC select in Administration.
     */
    public function description(): string
    {
        return match ($this) {
            self::ManpowerShortage => 'Production volume is up while manpower did not grow.',
            self::CategoryDominant => 'One overtime category holds most of the hours.',
            self::EmployeeConcentration => 'The top employees hold a large share of the hours.',
            self::HolidayOvertime => 'A large share of the hours was worked on non-working days.',
            self::UnplannedOvertime => 'Actual hours are clearly above the approved overtime plan.',
            self::Unknown => 'No other cause could be detected from the available data.',
        };
    }

    /**
     * Code (`departments.code`) of the default PIC department of the cause, or null when the PIC is the
     * alert's own department. Configured in `config/early_warning.php`.
     */
    public function defaultPicKey(): ?string
    {
        $code = config('early_warning.default_pic_department_codes.'.$this->value);

        return is_string($code) && $code !== '' ? $code : null;
    }

    /**
     * Position in the catalog (tie-break order, lower wins).
     */
    public function catalogOrder(): int
    {
        return (int) array_search($this, self::cases(), true);
    }
}
