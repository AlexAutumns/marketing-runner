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
    |--------------------------------------------------------------------------
    | Action rules
    |--------------------------------------------------------------------------
    |
    | These rules strengthen action behavior without turning the kernel into
    | an execution engine. They describe what the workflow definition must
    | provide for each supported action type.
    |
    | Keep these rules small and practical.
    |
    */
    'action_rules' => [
        'UPDATE_WORKFLOW_PROPERTY' => [
            'allowed_target_types' => ['CONTACT'],
            'required_payload_keys' => ['property_key', 'property_value'],
        ],

        'MARK_FOR_SCORING_HANDOFF' => [
            'allowed_target_types' => ['CONTACT'],
            'required_payload_keys' => ['handoff_reason'],
        ],

        'APPLY_LEAD_SCORE' => [
            'allowed_target_types' => ['CONTACT'],
            'required_payload_keys' => ['score_rule_code'],
        ],

        'UPDATE_LEAD_SUMMARY' => [
            'allowed_target_types' => ['CONTACT'],
            'required_payload_keys' => ['summary_code'],
        ],

        /*
         * SEND_EMAIL is included now as a forward-ready action family even
         * though the current seed does not actively use it yet.
         */
        'SEND_EMAIL' => [
            'allowed_target_types' => ['CONTACT'],
            'required_payload_keys' => ['template_key'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | CRM MVP handoff mapping
    |--------------------------------------------------------------------------
    |
    | These rules describe how workflow-emitted update instructions should be
    | shaped when preparing a handoff package that is closer to the official
    | CRM MVP semantics.
    |
    | This does not execute anything. It only gives us a stable mapping layer
    | between the workflow-kernel bundle and the future CRM-side handling path.
    |
    */
    'crm_mvp_handoff_rules' => [
        'UPDATE_CONTACT_LEAD_SCORE' => [
            'target_type_code' => 'SCORING_SERVICE',
            'target_id' => 'TS_SCORING',
            'reference_key' => 'score_rule_code',
            'reference_label' => 'lead_scoring_rule_code',
        ],

        'UPDATE_CONTACT_LEAD_SUMMARY' => [
            'target_type_code' => 'CRM_SERVICE',
            'target_id' => 'CRM_CONTACT_SUMMARY',
            'reference_key' => 'summary_code',
            'reference_label' => 'summary_code',
        ],

        /*
         * Forward reference only.
         * Included so the catalog direction stays coherent as SEND_EMAIL becomes
         * more active later.
         */
        'SEND_EMAIL' => [
            'target_type_code' => 'EMAIL_SERVICE',
            'target_id' => 'BP_EMAIL',
            'reference_key' => 'template_key',
            'reference_label' => 'email_template_key',
        ],
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
