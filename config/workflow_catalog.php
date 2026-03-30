<?php

return [

    /*
    --------------------------------------------------------------------------
     Supported workflow profiles
    --------------------------------------------------------------------------

     Keep this small. Only list profiles the system can actually validate
     and support right now.

    */
    'profiles' => [
        'WORKFLOW_KERNEL_BASELINE_V1',
        'EMAIL_SCORING_V1',
    ],
    /*
    --------------------------------------------------------------------------
     Supported step types
    --------------------------------------------------------------------------

     These must match the current runtime behavior in WorkflowEventProcessor.
     Do not add future types here until the processor and tests support them.

    */
    'step_types' => [
        'WAIT_FOR_EVENT',
        'WAIT_FOR_TIME',
        'TERMINAL',
    ],

    /*
    --------------------------------------------------------------------------
     Supported condition types
    --------------------------------------------------------------------------

     These must match the condition evaluators that currently exist in the
     workflow processor.

    */
    'condition_types' => [
        'event_type_in',
        'event_category_in',
        'event_source_in',
        'payload_field_exists',
    ],

    /*
    --------------------------------------------------------------------------
     Supported action types
    --------------------------------------------------------------------------

     Keep this limited to action intent types we currently recognize.
     These are configuration-level action codes, not provider execution details.

    */
    'action_types' => [
        'SEND_EMAIL',
        'APPLY_LEAD_SCORE',
        'UPDATE_LEAD_SUMMARY',

        // Existing branch/sample compatibility
        'UPDATE_WORKFLOW_PROPERTY',
        'MARK_FOR_SCORING_HANDOFF',
    ],

    /*
    --------------------------------------------------------------------------
     Supported source systems
    --------------------------------------------------------------------------

     This is not yet a full integration framework. These are just the source
     codes we currently allow inside workflow-facing event conditions.

    */
    'source_systems' => [
        'EMAIL_TRACKING',
        'FORM_CAPTURE',
        'SYSTEM_RESUME',
        'MANUAL_TEST',
    ],

    /*
    --------------------------------------------------------------------------
     Supported event types
    --------------------------------------------------------------------------

     Keep this to the event types we currently use in the seeded flow and the
     current MVP direction.

    */
    'event_types' => [
        'MANUAL_TEST_EVENT',
        'EMAIL_LINK_CLICKED',
        'EMAIL_DELIVERED',
        'EMAIL_BOUNCED',
        'EMAIL_OPENED',
        'BROCHURE_LINK_CLICKED',
        'FORM_SUBMITTED',
        'WAIT_TIMER_REACHED',
    ],

    /*
    --------------------------------------------------------------------------
     Supported event categories
    --------------------------------------------------------------------------
    */
    'event_categories' => [
        'ENGAGEMENT',
        'WORKFLOW_CONTROL',
    ],
];
