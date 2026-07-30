<?php

/**
 * SteadFast authority campaign — Step 6 cluster lock calendar.
 *
 * Mentor reads this for আজকের প্ল্যান when Authority campaign is active.
 * All themes stay inside SteadFast / fraud-check cluster until Step 10 win.
 * CTAs only on live app.wpsalehub.com paths (inventory wins).
 *
 * Week math: Monday-start weeks from lock_start_date.
 * Video days: Step 4 YouTube is deferred — treat Wed/Thu Week 4 as script outlines
 * or pillar/tool social demos until video_youtube_id is set.
 */

return [
    'active' => true,
    'cluster' => 'steadfast_fraud_check',
    'pillar_path' => '/steadfast-fraud-check',
    'free_tool_path' => '/bd-fraud-checker',
    'lock_start_date' => '2026-07-27', // Monday Week 1 (Asia/Dhaka)
    'lock_weeks' => 4,
    'notes' => [
        'Do not theme-hop to Messenger / AI / Pricing-only days while active.',
        'Feature-launch exception only if Shipped AND SteadFast/fraud adjacent (e.g. Return Hub).',
        'Sunday = GSC + Step 9 metrics — still SteadFast queries only.',
        'Step 4 YouTube deferred — Week 4 Wed/Thu = live demos / tip posts, not required YT publish.',
        'After Week 4: stay on SteadFast maintenance + deepen until Step 10 win; do not auto-expand to Pathao.',
    ],

    /*
     | Day keys: mon … sun within each lock week.
     | Fields: theme (BN), angle (EN short), cta, asset, checklist[], short_hook (optional)
     */
    'weeks' => [
        1 => [
            'label' => 'Week 1 — Pillar + tool awareness',
            'days' => [
                'mon' => [
                    'theme' => 'SteadFast Fraud Check পিলার',
                    'angle' => 'What SteadFast fraud check is (signal, not verdict)',
                    'cta' => '/steadfast-fraud-check',
                    'asset' => 'social + pillar CTA',
                    'checklist' => [
                        'Share pillar H1 + honesty line',
                        'CTA → /steadfast-fraud-check then /bd-fraud-checker',
                        '1 Muhibbullah byline beat',
                    ],
                    'short_hook' => 'SteadFast Fraud Check মানে কী? গ্যারান্টি নয়—সিগন্যাল।',
                ],
                'tue' => [
                    'theme' => 'Customer History কী',
                    'angle' => 'How to read courier history before confirm',
                    'cta' => '/blog/steadfast-customer-history-ki',
                    'asset' => 'social → blog → pillar',
                    'checklist' => [
                        'Post from history blog',
                        'Link hub: blog → /steadfast-fraud-check → /bd-fraud-checker',
                        'Threads tip: এক রিটার্ন ≠ চিরকাল ব্লক',
                    ],
                    'short_hook' => 'Customer history কী দেখায়—আর কী দেখায় না?',
                ],
                'wed' => [
                    'theme' => 'Delivery Ratio / সাকসেস রেট',
                    'angle' => 'Success ratio as risk signal',
                    'cta' => '/blog/steadfast-delivery-ratio-ki',
                    'asset' => 'social + FAQ',
                    'checklist' => [
                        'CTA blog + /faq/courier-success-rate-kivabe-bujhbo',
                        'Secondary: /faq/success-rate-kom-hole-ki-korbo',
                        'IG Reel: রেট পড়ার ৩ ধাপ',
                    ],
                    'short_hook' => 'সাকসেস রেট কম হলেই কি ফেক? না।',
                ],
                'thu' => [
                    'theme' => 'ফ্রি টুলে নম্বর চেক',
                    'angle' => 'Demo the free checker workflow',
                    'cta' => '/bd-fraud-checker',
                    'asset' => 'demo Short + tool CTA',
                    'checklist' => [
                        'Screen/demo: number → history → decide',
                        'Pillar soft-link /steadfast-fraud-check',
                        'Honesty line once',
                    ],
                    'short_hook' => '৩০ সেকেন্ডে নম্বর চেক—কনফার্মের আগে।',
                ],
                'fri' => [
                    'theme' => 'Founder: কেন এই ক্লাস্টার',
                    'angle' => 'Muhibbullah — why SteadFast authority first',
                    'cta' => '/steadfast-fraud-check',
                    'asset' => 'LinkedIn founder + /about',
                    'checklist' => [
                        'Founder face/byline',
                        'One Topic → One Authority story',
                        'CTA pillar; email only if collab ask',
                    ],
                    'short_hook' => 'এক টপিক আগে জিতব—তাই এখন শুধু SteadFast।',
                ],
                'sat' => [
                    'theme' => 'কখন কাস্টমার ভেরিফাই',
                    'angle' => 'Risk-based verify policy',
                    'cta' => '/blog/kokhon-customer-verify-korbo',
                    'asset' => 'social + FAQ',
                    'checklist' => [
                        'Blog + /faq/prottek-customer-verify-korbo-ki',
                        'Tie to /customer-verification only as secondary',
                        'Avoid Messenger theme',
                    ],
                    'short_hook' => 'প্রত্যেক কাস্টমার ভেরিফাই? রিস্ক জোন দেখুন।',
                ],
                'sun' => [
                    'theme' => 'GSC + SteadFast মেট্রিক্স',
                    'angle' => 'Step 9 Sunday review (cluster only)',
                    'cta' => '/steadfast-fraud-check',
                    'asset' => 'metrics note + optional FAQ refresh',
                    'checklist' => [
                        'GSC: steadfast / fraud check / success rate queries',
                        'Tool clicks: /bd-fraud-checker + pillar',
                        'Note 1 winner query → next FAQ deepen (not new theme)',
                    ],
                    'short_hook' => null,
                ],
            ],
        ],
        2 => [
            'label' => 'Week 2 — FAQ depth + mistakes',
            'days' => [
                'mon' => [
                    'theme' => 'ফোন কনফার্ম ≠ গ্যারান্টি',
                    'angle' => 'Phone confirm is not delivery guarantee',
                    'cta' => '/faq/phone-confirm-delivery-guarantee-ki',
                    'asset' => 'FAQ + Short',
                    'checklist' => [
                        'FAQ CTA + pillar',
                        'Honesty + layers (check → call → OTP)',
                    ],
                    'short_hook' => 'ফোনে “নিবো” বলেই কি ডেলিভারি নিশ্চিত?',
                ],
                'tue' => [
                    'theme' => 'হিস্টোরি না থাকলে কী করব',
                    'angle' => 'Empty history SOP',
                    'cta' => '/faq/history-na-thakle-ki-korbo',
                    'asset' => 'FAQ social',
                    'checklist' => [
                        'FAQ + /blog/steadfast-customer-history-ki',
                        'Tool CTA /bd-fraud-checker',
                    ],
                    'short_hook' => 'হিস্টোরি খালি = নিরাপদ? না।',
                ],
                'wed' => [
                    'theme' => 'Common mistakes',
                    'angle' => '10 seller mistakes with SteadFast check',
                    'cta' => '/blog/steadfast-fraud-check-common-mistakes',
                    'asset' => 'carousel / Threads list',
                    'checklist' => [
                        'Pick 3 mistakes for posts',
                        'Loop link to pillar + FAQ index',
                    ],
                    'short_hook' => 'চেক আছে তবু রিটার্ন বাড়ে—কোন ভুল?',
                ],
                'thu' => [
                    'theme' => 'টুল কি ফ্রড predict করে?',
                    'angle' => 'Honesty — no fake/genuine guarantee',
                    'cta' => '/faq/wooeasylife-fraud-predict-kore-ki',
                    'asset' => 'trust post + FAQ',
                    'checklist' => [
                        'Mandatory honesty line',
                        'CTA /fake-customer-check secondary + pillar primary',
                    ],
                    'short_hook' => 'WooEasyLife কি বলে দেয় ফেক না জেনুইন?',
                ],
                'fri' => [
                    'theme' => 'Founder trust / limitation',
                    'angle' => 'What the tool cannot do',
                    'cta' => '/steadfast-fraud-check',
                    'asset' => 'LinkedIn + about',
                    'checklist' => [
                        'Limitations beat builds trust',
                        'CTA pillar FAQ accordion',
                    ],
                    'short_hook' => 'টুলের সীমা বললেই বিশ্বাস বাড়ে।',
                ],
                'sat' => [
                    'theme' => 'ফেক অর্ডার চিনা যায় কি',
                    'angle' => 'Signals vs certainty',
                    'cta' => '/faq/fake-order-chinhe-fela-jay-ki',
                    'asset' => 'FAQ + protection soft-link',
                    'checklist' => [
                        'FAQ CTA',
                        'Soft /fake-order-protection (cluster-adjacent OK)',
                        'Stay BN COD language',
                    ],
                    'short_hook' => 'ফেক অর্ডার ১০০% চিনা যায়? না—লেয়ার লাগে।',
                ],
                'sun' => [
                    'theme' => 'GSC — FAQ winners',
                    'angle' => 'Which FAQ got impressions?',
                    'cta' => '/blog/steadfast-fraud-check-faq',
                    'asset' => 'FAQ index refresh or deepen one thin FAQ',
                    'checklist' => [
                        'Rank FAQ impressions',
                        'Deepen one weak FAQ toward 600–1000 tokens if needed',
                        'No new cluster outside SteadFast',
                    ],
                    'short_hook' => null,
                ],
            ],
        ],
        3 => [
            'label' => 'Week 3 — Case study + return math',
            'days' => [
                'mon' => [
                    'theme' => 'কেস স্টাডি ১৮%→১২%',
                    'angle' => 'Return rate case narrative',
                    'cta' => '/blog/steadfast-fraud-check-case-study',
                    'asset' => 'case carousel + LinkedIn',
                    'checklist' => [
                        'Numbers + process, no fake AggregateRating',
                        'CTA case → calculator → pillar',
                    ],
                    'short_hook' => 'রিটার্ন ১৮% থেকে ১২%—কী বদলেছিল?',
                ],
                'tue' => [
                    'theme' => 'রিটার্ন লস ক্যালকুলেটর',
                    'angle' => 'Monthly loss math',
                    'cta' => '/return-loss-calculator',
                    'asset' => 'tool demo + FAQ',
                    'checklist' => [
                        'Calculator CTA',
                        '/faq/cod-return-loss-hisab',
                        'Case study soft-link',
                    ],
                    'short_hook' => 'মাসে রিটার্নে কত টাকা পোড়ে—৩ ইনপুটে দেখুন।',
                ],
                'wed' => [
                    'theme' => 'SteadFast রিটার্ন কমানো',
                    'angle' => 'Return reduction playbook',
                    'cta' => '/blog/steadfast-return-komano',
                    'asset' => 'social playbook',
                    'checklist' => [
                        'Blog → pillar → tool',
                        'Mention Return Hub only as post-booking layer',
                    ],
                    'short_hook' => 'রিটার্ন কমাতে প্রি-শিপ চেকই যথেষ্ট নয়।',
                ],
                'thu' => [
                    'theme' => 'Return Request / Decide',
                    'angle' => 'Post-booking SteadFast return ops',
                    'cta' => '/faq/steadfast-return-request-kivabe',
                    'asset' => 'FAQ + /steadfast-return-hub',
                    'checklist' => [
                        'FAQ + Return Hub landing',
                        'Clarify: not a substitute for fraud check',
                        'Pillar still in every post',
                    ],
                    'short_hook' => 'SteadFast return request এলে Decide কীভাবে?',
                ],
                'fri' => [
                    'theme' => 'Founder: লস মাথায় রাখো',
                    'angle' => 'Ops loss before ad scale',
                    'cta' => '/return-loss-calculator',
                    'asset' => 'LinkedIn founder',
                    'checklist' => [
                        'Muhibbullah + calculator CTA',
                        'Tie to case study',
                    ],
                    'short_hook' => 'অ্যাড বাড়ানোর আগে রিটার্ন লস মাপুন।',
                ],
                'sat' => [
                    'theme' => 'Stuck parcel কী করব',
                    'angle' => 'Stuck / silent parcel SOP',
                    'cta' => '/faq/steadfast-stuck-parcel-ki-korbo',
                    'asset' => 'FAQ + Return Hub',
                    'checklist' => [
                        'FAQ CTA',
                        '/steadfast-return-hub secondary',
                        'No Paperfly / Messenger hop',
                    ],
                    'short_hook' => 'পার্সেল আটকে গেলে আগে কী দেখবেন?',
                ],
                'sun' => [
                    'theme' => 'GSC — tool + calculator clicks',
                    'angle' => 'Step 9: clicks to tool & calculator',
                    'cta' => '/bd-fraud-checker',
                    'asset' => 'metrics + CTR tweak on pillar title if needed',
                    'checklist' => [
                        'Compare week 1–3 impressions on pillar',
                        'Tool + calculator click trend',
                        'One title/meta tweak max on underperforming URL',
                    ],
                    'short_hook' => null,
                ],
            ],
        ],
        4 => [
            'label' => 'Week 4 — Loop reinforce (+ optional YouTube outlines)',
            'days' => [
                'mon' => [
                    'theme' => 'Closed loop রিমাইন্ডার',
                    'angle' => 'Guide → FAQ → Tool → Case → Calc → Guide',
                    'cta' => '/steadfast-fraud-check',
                    'asset' => 'cluster map post',
                    'checklist' => [
                        'Show loop visually (video node optional while Step 4 deferred)',
                        'All CTAs live paths only',
                    ],
                    'short_hook' => 'এক ক্লাস্টার—ছয় নোডে ঘোরান।',
                ],
                'tue' => [
                    'theme' => 'FAQ ইনডেক্স রাউন্ডআপ',
                    'angle' => 'SteadFast FAQ index',
                    'cta' => '/blog/steadfast-fraud-check-faq',
                    'asset' => 'listicle social',
                    'checklist' => [
                        'Index → top 3 FAQs',
                        'Hub /faq secondary',
                    ],
                    'short_hook' => 'SteadFast নিয়ে সব প্রশ্ন এক জায়গায়।',
                ],
                'wed' => [
                    'theme' => 'Complete Guide demo (live tool)',
                    'angle' => 'Step 4 deferred — demo pillar/tool in-browser instead of Long video',
                    'cta' => '/steadfast-fraud-check',
                    'asset' => 'screen demo social OR optional Long script outline only',
                    'checklist' => [
                        'Live checker walkthrough on /steadfast-fraud-check or /bd-fraud-checker',
                        'Optional: outline Complete Guide Long for later YouTube',
                        'CTA pillar + tool — no fake “video live” claim',
                    ],
                    'short_hook' => null,
                ],
                'thu' => [
                    'theme' => '৫টি এক-প্রশ্ন টিপ (Short-ready)',
                    'angle' => 'Step 4 deferred — post as Reels/Threads; save as Short scripts for later',
                    'cta' => '/bd-fraud-checker',
                    'asset' => '5 tip posts (optional YouTube Shorts later)',
                    'checklist' => [
                        'Hooks from weeks 1–3 short_hook list',
                        'Each tip one question only',
                        'CTA app.wpsalehub.com pillar — do not claim YT embed until published',
                    ],
                    'short_hook' => 'আজকের টিপ: একটি প্রশ্ন—একটি উত্তর।',
                ],
                'fri' => [
                    'theme' => 'Founder wrap Week 4',
                    'angle' => 'Patience = authority',
                    'cta' => '/steadfast-fraud-check',
                    'asset' => 'LinkedIn + about',
                    'checklist' => [
                        'Why we did not hop themes',
                        'Invite sellers to try free check',
                    ],
                    'short_hook' => '৪ সপ্তাহ এক টপিক—এটাই অথরিটির ধৈর্য।',
                ],
                'sat' => [
                    'theme' => 'Fake Customer Check (adjacent)',
                    'angle' => 'Same engine, customer-verify angle',
                    'cta' => '/fake-customer-check',
                    'asset' => 'soft expand inside fraud cluster',
                    'checklist' => [
                        'Still fraud cluster — not Pathao campaign',
                        'Link back to /steadfast-fraud-check',
                        'Honesty line',
                    ],
                    'short_hook' => 'Fake customer check = কনফার্মের আগে নম্বর যাচাই।',
                ],
                'sun' => [
                    'theme' => 'Week 4 GSC + Step 10 gate check',
                    'angle' => 'Are we winning enough to expand?',
                    'cta' => '/steadfast-fraud-check',
                    'asset' => 'decision memo',
                    'checklist' => [
                        'Position/CTR/impressions/tool clicks trends',
                        'If NOT top results + meaningful traffic → extend SteadFast lock (do not start Pathao)',
                        'If winning → plan Step 10 Pathao only after explicit call',
                    ],
                    'short_hook' => null,
                ],
            ],
        ],
    ],
];
