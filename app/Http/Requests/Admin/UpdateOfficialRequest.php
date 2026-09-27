<?php

namespace App\Http\Requests\Admin;

/**
 * Administrator editing an existing official.
 *
 * Same rules as creation — kept as its own class so editing can grow its
 * own rules (e.g. relaxing term dates for historical entries) later.
 */
class UpdateOfficialRequest extends StoreOfficialRequest
{
}
