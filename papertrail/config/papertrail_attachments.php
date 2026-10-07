<?php

return [
    'purchase_request' => [
        'required' => [
            'APP reference / Supplemental APP',
            'Supporting quotation if applicable',
        ],
    ],
    'bac_resolution' => [
        'required' => [
            'Purchase Request',
            'RFQ / Quotation documents',
            'Abstract of Quotations',
        ],
    ],
    'rfq' => [
        'required' => [
            'BAC Resolution',
            'Purchase Request',
            'Supplier quotation records',
        ],
    ],
    'abstract' => [
        'required' => [
            'RFQ / Quotation documents',
            'Supplier quotations',
        ],
    ],
    'purchase_order' => [
        'required' => [
            'Abstract of Quotations',
            'Supplier award details',
        ],
    ],
    'inspection_acceptance' => [
        'required' => [
            'Purchase Order',
            'Delivery receipt',
            'Inspection notes',
        ],
    ],
];
