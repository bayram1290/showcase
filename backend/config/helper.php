<?php

return [
    'default_guards' => ['sanctum'],
    'mobile_format_verification' => '^993[6,7][0-5][0-9]{6}$',
    'auth' => [
        'login_format' => "^[a-zA-Z0-9_]+$",
        'password_format' => "^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$",
        'monthly_income_format' => "^\d+(\.\d{1,2})?$",
        'employment_statuses' => ["employed", "self-employed", "unemployed", "retired", "student"],
        'ssn_format' => "^[0-9]{9}$",
        'government_id_types' => ["passport", "nic", "drivers_license", "eid"],
        'monthly_expenses_format' => "^\d+(\.\d{1,2})?$",
        'contact_methods' => ["email", "phone", "sms"],
        'marital_statuses' => ["single", "married", "divorced", "widowed"]
    ],
    'default_query_get_limit' => 10,
    'notify_admin_on_new_loan_submission' => false,
    'default_pagination_length' => 15
];