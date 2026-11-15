<?php

namespace App\Modules\PodPlan\Support;

/**
 * Field of view for the camera cones on the plan, worked out from the names
 * in the inventory: the focal length from the lens ("Meike 35mm", or the wide
 * end of a zoom such as "Canon 70-200mm") and the reduction of a speed
 * booster from its name ("... XL 0.64x").
 */
class Optics
{
    /** Sensor width in mm: the Blackmagic 4K Micro Four Thirds sensor. */
    public const SENSOR_WIDTH = 18.96;

    /** Cone drawn for a camera whose lens is not chosen yet. */
    public const DEFAULT_FOV = 52.0;

    /** Beam drawn for a light until it is set by hand. */
    public const LIGHT_FOV = 60.0;

    public static function focal(?string $name): ?float
    {
        if ($name && preg_match('/(\d+(?:\.\d+)?)\s*(?:-\s*\d+(?:\.\d+)?\s*)?mm\b/i', $name, $m)) {
            return (float) $m[1] > 0 ? (float) $m[1] : null;
        }

        return null;
    }

    public static function factor(?string $name): float
    {
        if ($name && preg_match('/\b(0\.\d+)\s*x\b/i', $name, $m)) {
            return (float) $m[1];
        }

        return 1.0;
    }

    /** Horizontal angle of view in degrees. */
    public static function fov(float $focal, float $factor = 1.0): float
    {
        $angle = rad2deg(2 * atan(self::SENSOR_WIDTH / (2 * $focal * $factor)));

        return round(max(4.0, min(110.0, $angle)), 1);
    }
}
