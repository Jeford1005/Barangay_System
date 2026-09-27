<?php

namespace App\Http\Requests;

/**
 * Administrator editing a purok. Unique rules ignore the record itself, and a
 * blank code means "keep the current code" (the column is NOT NULL).
 */
class UpdatePurokRequest extends PurokFormRequest
{
}
