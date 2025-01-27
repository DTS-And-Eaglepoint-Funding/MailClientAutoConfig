<?php
return [
    'fake_dns_records' => 
    [
        'test.txt' => 
        [
            'TXT' => 
            [
                0 => 'mailconfig-imap=imap.txt-test.local:993:SSL',
                1 => 'mailconfig-pop3=pop3.txt-test.local:995:SSL',
                2 => 'mailconfig-smtp=smtp.txt-test.local:465:SSL',
                3 => 'mailconfig-caldav=caldav.txt-test.local:443:SSL',
                4 => 'mailconfig-carddav=carddav.txt-test.local:443:SSL',
                5 => 'mailconfig-activesync=activesync.txt-test.local:443:SSL'
            ],
        ],
        'mtaststest.shorttxt' => 
        [
            'TXT' => 
            [
                0 => 'mailconfig-mtasts=enforce:60000',
            ],
            'MX' => 
            [
                0 => 
                [
                    'host' => 'aspmx.mtaststest.shorttxt',
                    'pri' => 1,
                ],
                1 => 
                [
                    'host' => 'alt2.aspmx.mtaststest.shorttxt',
                    'pri' => 10,
                ],
            ],
        ],
        'mtaststest.longtxt' => 
        [
            'TXT' => 
            [
                0 => 'mailconfig-mtasts=mx1.mtaststest.longtxt,mx2.mtaststest.longtxt:enforce:604800',
            ]
        ],
        'mtaststest.mxnotxt' => 
        [
            'MX' => 
            [
                0 => 
                [
                    'host' => 'aspmx.mtaststest.shorttxt',
                    'pri' => 1,
                ],
                1 => 
                [
                    'host' => 'alt2.aspmx.mtaststest.shorttxt',
                    'pri' => 10,
                ],
            ],
        ],
        'test.srv' => 
        [
            'SRV' => 
            [
                0 => 
                [
                    'service' => 'imaps',
                    'protocol' => 'tcp',
                    'target' => 'imap.srv-test.local',
                    'port' => 993,
                    'pri' => 10,
                    'weight' => 10,
                ],
                1 => 
                [
                    'service' => 'pop3s',
                    'protocol' => 'tcp',
                    'target' => 'pop3.srv-test.local',
                    'port' => 995,
                    'pri' => 10,
                    'weight' => 10,
                ],
                2 => 
                [
                    'service' => 'submission',
                    'protocol' => 'tcp',
                    'target' => 'smtp.srv-test.local',
                    'port' => 587,
                    'pri' => 10,
                    'weight' => 10,
                ],
                3 => 
                [
                    'service' => 'autodiscover',
                    'protocol' => 'tcp',
                    'target' => 'activesync.srv-test.local',
                    'port' => 443,
                    'pri' => 10,
                    'weight' => 10,
                ],
            ],
        ],
        'test.ispd' => 
        [
            'MX' => 
            [
                0 => 
                [
                    'host' => 'aspmx.l.google.com',
                    'pri' => 1,
                ],
                1 => 
                [
                    'host' => 'alt2.aspmx.l.google.com',
                    'pri' => 10,
                ],
            ],
        ],
        'localhost' => 
        [
            'MX' => 
            [
                0 => 
                [
                    'host' => 'aspmx.l.google.com',
                    'pri' => 1,
                ],
                1 => 
                [
                    'host' => 'alt2.aspmx.l.google.com',
                    'pri' => 10,
                ],
            ]
        ],
        'test.notispd' => 
        [
            'MX' => 
            [
                0 => 
                [
                    'host' => 'mx1.hostinger.com',
                    'pri' => 1,
                ],
                1 => 
                [
                    'host' => 'mx2.hostinger.com',
                    'pri' => 10,
                ],
            ],
        ],
        'test.fallback' => 
        [
            'NS' => 
            [
                0 => 'ns1.dns-parking.com',
            ],
        ],
        'test.invalid' => 
        [
            'A' => 
            [
                0 => 'not an ip',
            ],
        ],
        '_mta-sts.localhost' => 
        [
            'TXT' => 
            [
                0 => 'v=STSv1; id=20190101T000000;',
            ],
        ],
    ],
];