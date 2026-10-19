<?php

/**
 * Default duties for the appointment letters.
 *
 * When HR issues a contract they choose Academic or Non-academic staff and the matching list
 * below is filled into the "Duties" box. It is only a starting point: HR can edit, add or
 * remove lines for each contract before generating it. Each line becomes one bullet in the
 * letter, so keep one duty per line.
 *
 * To change what the defaults say, edit the lines below.
 */
return [
    'academic' => [
        'To contribute in teaching, prepare examination question papers and marking papers, prepare Education Ministry documentation and any other relevant duties.',
        'To participate in education counselling and marketing activities.',
        'Maintaining student records online that include their registration details and other report as necessary.',
        'To be involved in the curriculum development.',
        'To work as a team on scholarships and other events, when necessary, that can promote the objectives, vision and mission of Lincoln University.',
        'To carry out significant research and publish in reputable international journals.',
        'To design and implement projects presented by Lincoln University.',
        'Other duties as instructed by the Department Coordinator.',
    ],

    'non_academic' => [
        'To carry out the duties and responsibilities of your position as set out in your job description.',
        'To maintain accurate records and documentation for your office and keep all University information confidential.',
        'To prepare and submit regular reports on your work to your Head of Department/Unit.',
        'To work as a team and cooperate with other departments and units of the University.',
        'To support student recruitment, University events and other activities when necessary, that can promote the objectives, vision and mission of Lincoln University.',
        'To comply with the policies, procedures and regulations of the University.',
        'Other duties as instructed by your Head of Department/Unit.',
    ],
];
