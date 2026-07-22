/**
 * Recover readable structure from flattened cluster guide copy
 * (Word/PDF paste often mashed tables, subsections, and templates).
 */

const BN_DIGITS = '০১২৩৪৫৬৭৮৯';
const SUB_ID_SRC = '(?:[০-৯]{1,2}|\\d{1,2})\\.(?:[০-৯]{1,2}|\\d{1,2})';

const COMPARE_HEADER_PAIRS = [
    ['ট্র্যাডিশনাল উকমার্স ফরম', 'ওয়ান-ক্লিক ফাস্ট চেকআউট ফরম'],
    ['Traditional WooCommerce form', 'One-click fast checkout form'],
    ['অটোমেটেড এসএমএস (SMS)', 'হোয়াটসঅ্যাপ'],
    ['Automated SMS', 'WhatsApp'],
    ['অটোমেশন ছাড়া', 'অটোমেশনসহ'],
    ['Without automation', 'With automation'],
    ['ম্যানুয়াল', 'অটো'],
    ['Manual', 'Auto'],
];

const COMPARE_ROW_KEYS = [
    'ইনপুট ফিল্ড সংখ্যা', 'অর্ডার সম্পূর্ণ করতে সময়', 'মোবাইল ফ্রেন্ডলিনেস',
    'জেলা/থানা সিলেক্ট', 'গড় কনভার্সন রেট',
    'Input fields', 'Time to complete order', 'Mobile friendliness',
    'District/thana select', 'Avg conversion rate',
    'ডেলিভারি রেট', 'কস্ট প্রতি মেসেজ', 'ওপেন রেট', 'রিচ মিডিয়া সাপোর্ট', 'ব্যবহারের ক্ষেত্র',
    'Delivery rate', 'Cost per message', 'Open rate', 'Rich media', 'Best used for',
    'গুগল অ্যাট্রিবিউট', 'Google attribute', 'উকমার্স ফিল্ড', 'WooCommerce field',
    'উদাহরণ', 'Example', 'ইভেন্ট (Event)', 'Event', 'ট্রিগার', 'Trigger', 'অ্যাকশন', 'Action',
];

/** 3-column matrix tables mashed without separators */
const MATRIX_SPECS = [
    {
        headers: ['সেগমেন্ট', 'বৈশিষ্ট্য', 'মার্কেটিং স্ট্র্যাটেজি'],
        headerMash: 'সেগমেন্টবৈশিষ্ট্যমার্কেটিং স্ট্র্যাটেজি',
        rows: [
            'VIP Customers',
            'Regular Customers',
            'At-Risk / Dormant',
        ],
    },
    {
        headers: ['Customer segment', 'Traits', 'Marketing strategy'],
        headerMash: 'Customer segmentTraitsMarketing strategy',
        rows: ['VIP Customers', 'Regular Customers', 'At-Risk / Dormant'],
    },
    {
        headers: ['মেম্বারশিপ টায়ার', 'মিনিমাম পারচেজ', 'এক্সক্লুসিভ সুবিধা'],
        headerMash: 'মেম্বারশিপ টায়ারমিনিমাম পারচেজ টার্গেটএক্সক্লুসিভ সুবিধা ও পার্কস (Perks)',
        rows: ['Silver Member', 'Gold Member', 'Platinum VIP'],
    },
    {
        headers: ['উকমার্স অ্যাকশন', 'GA4 ইভেন্ট', 'ডাটা প্যারামিটার'],
        headerMash: 'উকমার্স অ্যাকশনGA4 ই-কমার্স ইভেন্ট নামডাটা প্যারামিটার (Data Parameters)',
        rows: ['প্রোডাক্ট দেখা', 'কার্টে যুক্ত করা', 'চেকআউট শুরু', 'অর্ডার সম্পূর্ণ হওয়া'],
    },
    {
        headers: ['কাস্টমার সেগমেন্ট', 'বিহেভিওর', 'অটোমেটেড অ্যাকশন'],
        headerMash: 'কাস্টমার সেগমেন্টবিহেভিওরাল বৈশিষ্ট্যঅটোমেটেড অ্যাকশন / মেসেজিং ফানেল',
        rows: ['VIP Champions', 'At-Risk VIPs', 'One-Time Buyers'],
    },
    {
        headers: ['বৈশিষ্ট্য', 'সাধারণ ব্রাউজার পিক্সেল', 'সার্ভার-সাইড CAPI'],
        headerMash: 'বৈশিষ্ট্যসাধারণ ব্রাউজার পিক্সেলসার্ভার-সাইড কনভার্সন এপিআই (CAPI)',
        rows: [
            'ডাটা সেন্ডিং মাধ্যম',
            'অ্যাড ব্লকার প্রভাব',
            'ডাটা সঠিকতা',
            'ইভেন্ট ম্যাচ কোয়ালিটি (EMQ)',
        ],
    },
    {
        headers: ['Feature', 'Browser Pixel', 'Server CAPI'],
        headerMash: 'FeatureBrowser PixelServer-side Conversions API (CAPI)',
        rows: [
            'Data sending method',
            'Ad blocker impact',
            'Data accuracy',
            'Event Match Quality (EMQ)',
        ],
    },
    {
        headers: ['KPI মেট্রিক', 'সংজ্ঞা ও বিবরণ', 'টার্গেট বেঞ্চমার্ক'],
        headerMash: 'KPI মেট্রিকসংজ্ঞা ও বিবরণটার্গেট বেঞ্চমার্ক (BD E-commerce)',
        rows: [
            'AOV (Average Order Value)',
            'CAC (Customer Acquisition Cost)',
            'LTV:CAC Ratio',
            'RTS Rate (Return Rate)',
        ],
    },
    {
        headers: ['KPI metric', 'Definition', 'Target benchmark'],
        headerMash: 'KPI metricDefinition & detailTarget benchmark (BD E-commerce)',
        rows: [
            'AOV (Average Order Value)',
            'CAC (Customer Acquisition Cost)',
            'LTV:CAC Ratio',
            'RTS Rate (Return Rate)',
        ],
    },
];

function toAsciiDigitChar(ch) {
    const i = BN_DIGITS.indexOf(ch);
    return i >= 0 ? String(i) : ch;
}

function normalizeDigits(str) {
    return String(str).replace(/[০-৯]/g, toAsciiDigitChar);
}

/**
 * Split mega paragraphs on subsection markers like ১২.১ / 12.3
 * and on obvious template / table headers.
 */
function preSplitMegaText(text) {
    let raw = String(text || '').trim();
    if (!raw) return [];

    // Normalize odd chars
    raw = raw.replace(/\u0438/g, 'i'); // Cyrillic i from "или"

    if (raw.length < 220) return [raw];

    const markers = [];
    const subRe = new RegExp(`(?:^|(?<=[।.!?\\n]))\\s*(${SUB_ID_SRC})\\s+`, 'gu');
    for (const m of raw.matchAll(subRe)) {
        markers.push({ idx: m.index + m[0].indexOf(m[1]), label: m[1] });
    }

    // Also catch mashed "।১২.১" / "%।১২.১" with no space
    const mashSubRe = new RegExp(`(${SUB_ID_SRC})\\s+(?=[^\\d০-৯])`, 'gu');
    for (const m of raw.matchAll(mashSubRe)) {
        if (m.index > 20) markers.push({ idx: m.index, label: m[1] });
    }

    // Also split before known matrix headers if mid-paragraph
    for (const spec of MATRIX_SPECS) {
        const idx = raw.indexOf(spec.headerMash);
        if (idx > 40) markers.push({ idx, label: null });
    }

    // Split before quoted long templates
    const quoteRe = /(?<=[:：])\s*"[^"]{40,}/g;
    for (const m of raw.matchAll(quoteRe)) {
        markers.push({ idx: m.index, label: null });
    }

    markers.sort((a, b) => a.idx - b.idx);
    const uniq = [];
    for (const m of markers) {
        if (!uniq.length || m.idx - uniq[uniq.length - 1].idx > 8) uniq.push(m);
    }

    if (!uniq.length) {
        return splitLongProse(raw);
    }

    const parts = [];
    if (uniq[0].idx > 0) {
        parts.push(...splitLongProse(raw.slice(0, uniq[0].idx).trim()));
    }
    for (let i = 0; i < uniq.length; i += 1) {
        const start = uniq[i].idx;
        const end = i + 1 < uniq.length ? uniq[i + 1].idx : raw.length;
        const chunk = raw.slice(start, end).trim();
        if (chunk) parts.push(...splitLongProse(chunk));
    }
    return parts.filter(Boolean);
}

/** Break long prose on danda/period into ~2-sentence chunks */
function splitLongProse(text) {
    const raw = String(text || '').trim();
    if (!raw) return [];
    if (raw.length < 320) return [raw];

    // Keep ASCII diagrams / LaTeX / mashed tables intact
    if (/[┌├└┼┬┐┘]|\$\$|──[>┐┘┼]|KPI মেট্রিক|বৈশিষ্ট্যসাধারণ/u.test(raw)) {
        return [raw];
    }

    // Don't split tables/flows
    if (/➔|->|→|সেগমেন্টবৈশিষ্ট্য|VIP Customers|Silver Member|view_item/i.test(raw)
        && raw.length < 900) {
        return [raw];
    }

    // Split on sentence end — but never on numbered-list markers like "২. Database"
    const sentences = raw.split(/(?<=[।!?])\s+|(?<![০-৯0-9])(?<=[A-Za-zঅ-হা-য়)])\.\s+/u).filter(Boolean);
    if (sentences.length < 3) return [raw];

    const chunks = [];
    let buf = '';
    for (const s of sentences) {
        const next = buf ? `${buf} ${s}` : s;
        if (next.length > 280 && buf) {
            chunks.push(buf.trim());
            buf = s;
        } else {
            buf = next;
        }
    }
    if (buf.trim()) chunks.push(buf.trim());
    return chunks.length ? chunks : [raw];
}

function splitOnNumberedMarkers(text) {
    const raw = String(text || '').trim();
    if (!raw) return [];

    const re = /(?:^|(?<=[\s।.!?:：]))((?:[০-৯]|\d{1,2}))[.)]\s*/gu;
    const matches = [...raw.matchAll(re)];
    if (matches.length < 2) return [raw];

    const parts = [];
    for (let i = 0; i < matches.length; i += 1) {
        const absStart = matches[i].index + matches[i][0].indexOf(matches[i][1]);
        const absEnd = i + 1 < matches.length
            ? matches[i + 1].index + matches[i + 1][0].indexOf(matches[i + 1][1])
            : raw.length;
        const chunk = raw.slice(absStart, absEnd).trim();
        if (chunk) parts.push(chunk);
    }
    const firstAbs = matches[0].index + matches[0][0].indexOf(matches[0][1]);
    const lead = raw.slice(0, firstAbs).trim();
    return lead ? [lead, ...parts] : (parts.length ? parts : [raw]);
}

function parseKv(text) {
    const raw = String(text || '').trim();
    if (!raw || raw.length > 420) return null;
    const m = raw.match(/^(.{2,72}?)\s*:\s+(.+)$/u);
    if (!m) return null;
    const key = m[1].trim();
    const value = m[2].trim();
    if (!key || !value || key.length > 72) return null;
    if (/^https?:\/\//i.test(key)) return null;
    return { key, value };
}

function parseNumberedRow(text) {
    const raw = String(text || '').trim();
    const m = raw.match(/^((?:[০-৯]|\d{1,2}))[.)]\s+(.+)$/u);
    if (!m) return null;
    const rest = m[2].trim();
    const kv = parseKv(rest);
    if (kv) return { no: normalizeDigits(m[1]), item: kv.key, detail: kv.value };
    const dash = rest.match(/^(.{3,90}?)\s*[–—\-:]\s+(.+)$/u);
    if (dash) return { no: normalizeDigits(m[1]), item: dash[1].trim(), detail: dash[2].trim() };
    if (rest.length <= 160) return { no: normalizeDigits(m[1]), item: rest, detail: '' };
    return { no: normalizeDigits(m[1]), item: `${rest.slice(0, 72)}…`, detail: rest };
}

function parseSubsectionTitle(text) {
    const raw = String(text || '').trim();
    if (!new RegExp(`^${SUB_ID_SRC}\\s+`, 'u').test(raw)) return null;

    // Short English gloss in parentheses near the start: "… (Consumption Cycle)"
    let m = raw.match(new RegExp(`^(${SUB_ID_SRC})\\s+(.{6,70}?\\([A-Za-z][^\\)]{1,50}\\))`, 'u'));
    if (!m) {
        m = raw.match(new RegExp(
            `^(${SUB_ID_SRC})\\s+(.{8,90}?(?:অ্যানালিসিস|সিস্টেম|সিকোয়েন্স|সাইকেল|সেটআপ|ম্যাপিং|মডেল|স্ট্র্যাটেজি|আর্কিটেকচার|ফানেল|ম্যাট্রিক্স|Matrix))`,
            'u',
        ));
    }
    if (!m) {
        m = raw.match(new RegExp(`^(${SUB_ID_SRC})\\s+(.{8,80}?)(?=[A-Z][A-Za-z])`, 'u'));
    }
    if (!m) return null;

    const title = `${m[1]} ${m[2].trim()}`.trim();
    const rest = raw.slice(m[0].length).replace(/^[:：\s]+/, '').trim();
    if (title.length > 120) return null;
    return { title, rest };
}

function parseFlow(text) {
    const raw = String(text || '').trim();
    // Don't treat hub→branch ASCII trees / forks / merge junctions as linear flows
    if (/[┌├└┼┬┐┘]/.test(raw)) return null;
    if (!/[➔→]|->|──>/.test(raw)) return null;

    const steps = [];
    const bracketRe = /\[([^\]]{2,80})\]/g;
    let m;
    while ((m = bracketRe.exec(raw)) !== null) {
        steps.push(m[1].trim());
    }
    if (steps.length < 2) return null;

    const first = raw.indexOf('[');
    const lead = first > 0 ? raw.slice(0, first).trim().replace(/[:：]$/, '') : '';
    const afterLast = raw.slice(raw.lastIndexOf(']') + 1).trim();

    return {
        type: 'flow',
        lead: lead || null,
        steps,
        trailing: afterLast && afterLast.length > 12 && !/[┌├└┼┬]/.test(afterLast) ? afterLast : null,
    };
}

/**
 * Fork diagrams like:
 * [কাস্টমার অ্যাকশন] ──┬──> [Browser Pixel] ───> [Meta Server] (Data Loss High)
 *                     └──> [Server CAPI] ──────> [Meta Server] (100% Data Accuracy)
 */
function parseForkDiagram(text) {
    const raw = String(text || '').trim();
    if (!/┬/.test(raw)) return null;

    const rootMatch = raw.match(/\[([^\]]{2,80})\]\s*─*┬─*>?\s*/);
    if (!rootMatch) return null;

    const afterFork = raw.slice(rootMatch.index + rootMatch[0].length);
    const parts = afterFork.split(/\s*[├└]──?>\s*/);
    if (parts.length < 2) return null;

    const branches = parts.map((part) => {
        let chunk = String(part || '').trim();
        chunk = chunk
            .replace(/\s+বৈশিষ্ট্য[\s\S]*$/u, '')
            .replace(/\s+Feature[\s\S]*$/iu, '')
            .replace(new RegExp(`\\s+${SUB_ID_SRC}\\s+[\\s\\S]*$`, 'u'), '')
            .replace(/\s+(?:ড্যাশবোর্ডের|১৪\.|14\.|WPSaleHub)[\s\S]*$/u, '')
            .replace(/\s{2,}/g, ' ')
            .trim();
        // Normalize arrow glyphs for display
        chunk = chunk.replace(/─{2,}>/g, '──>').replace(/─{3,}/g, '───');
        return chunk;
    }).filter((b) => b.length >= 3 && b.length <= 200);

    if (branches.length < 2) return null;

    const lead = raw.slice(0, rootMatch.index).trim().replace(/[:：]$/, '');
    let trailing = null;
    const proseIdx = raw.search(/বৈশিষ্ট্য|FeatureBrowser|Feature\s*Browser/i);
    if (proseIdx > rootMatch.index) {
        trailing = raw.slice(proseIdx).trim();
    }

    return {
        type: 'tree',
        root: rootMatch[1].trim(),
        branches,
        lead: lead || null,
        trailing,
    };
}

/**
 * Recover mashed ASCII hub→branch trees into a clean diagram model.
 * Also covers decision gates:
 * [Incoming] ➔ [WAF] ├── 🟢 allow ➔ [Site] └── 🔴 block ➔ [Captcha]
 */
function parseTree(text) {
    const raw = String(text || '').trim();
    if (/┬/.test(raw)) return null; // handled by parseForkDiagram
    if (/[┐┘]/.test(raw) && /┼/.test(raw)) return null; // handled by parseMergeFlow
    if (!/[┌├└┼]/.test(raw)) return null;

    const markerRe = /[┌├└┼]\s*─*[>┼]?\s*/g;
    const markers = [...raw.matchAll(markerRe)];
    if (markers.length < 2) return null;

    const firstTree = markers[0].index;

    // Prefer hub label sitting just before the branch rail
    const beforeTree = raw.slice(0, firstTree);
    const nearRoots = [...beforeTree.matchAll(/\[([^\]]{2,80})\]/g)].map((m) => m[1].trim());
    const allRoots = [...raw.matchAll(/\[([^\]]{2,80})\]/g)].map((m) => m[1].trim());
    const preferred = allRoots.find((r) => (
        /Dashboard|Central|Stock|Customer Data|Audit|Tax|WAF|Cloudflare|ইনবক্স|Quarterly|Live Database/i.test(r)
    ));
    const root = nearRoots[nearRoots.length - 1] || preferred || allRoots[0] || 'Hub';

    const branches = [];
    for (let i = 0; i < markers.length; i += 1) {
        const start = markers[i].index + markers[i][0].length;
        const end = i + 1 < markers.length ? markers[i + 1].index : raw.length;
        let chunk = raw.slice(start, end).trim();

        // Drop only the hub label if it was mashed between branches
        chunk = chunk.replace(new RegExp(`\\[${escapeRegExp(root)}\\]`, 'g'), ' ').trim();

        // Cut trailing prose after last destination node / note
        const lastClose = Math.max(chunk.lastIndexOf(']'), chunk.lastIndexOf(')'));
        if (lastClose > 2) {
            const after = chunk.slice(lastClose + 1).trim();
            if (after.length > 8 && !/^[➔→─(]/.test(after)) {
                chunk = chunk.slice(0, lastClose + 1).trim();
            }
        }

        chunk = chunk
            .replace(/\s+(?:সিকিউরিটি কনফিগারেশন|ড্যাশবোর্ডের|স্টক অটোমেশন|সেন্টিমেন্ট|ট্যাক্স ও|স্মার্ট রাউটিং|হাই-কনভার্টিং|ব্যাকআপ টাইপ|এপিআই ও|API Reliability)[\s\S]*$/u, '')
            .replace(/\s+(?:[০-৯]|\d+)\.\s+(?:রেট|এপিআই|মেটা|API Reliability)[\s\S]*$/u, '')
            .replace(/\s+(?:[০-৯]|\d+)\.\s*$/u, '')
            .replace(/[।].*$/u, '')
            .replace(/\s{2,}/g, ' ')
            .trim()
            .replace(/\s*─+\s*$/u, '')
            .trim();

        if (chunk.length >= 2 && chunk.length <= 180) {
            // "৩.Security" → "৩. Security"
            chunk = chunk.replace(/^((?:[০-৯]|\d+)\.)(?=\S)/u, '$1 ');
            branches.push(chunk);
        }
    }

    if (branches.length < 2) return null;

    // Inbound nodes before hub (e.g. Incoming → WAF)
    let inbound = null;
    let lead = null;
    if (nearRoots.length > 1) {
        inbound = nearRoots.slice(0, -1);
    } else if (firstTree > 0) {
        lead = beforeTree
            .replace(new RegExp(`\\[${escapeRegExp(root)}\\]\\s*$`), '')
            .replace(/\s*[➔→\-─]+\s*$/u, '')
            .trim()
            .replace(/[:：]$/, '');
        if (!lead) lead = null;
    }

    let trailing = null;
    const proseStart = raw.search(/(?:সিকিউরিটি কনফিগারেশন|ড্যাশবোর্ডের মূল|স্টক অটোমেশন|সেন্টিমেন্ট অ্যানালিসিস|ট্যাক্স ও ইনভয়েস|স্মার্ট রাউটিং|হাই-কনভার্টিং কার্ট|এপিআই ও ওয়েবহুক|API Reliability)/u);
    if (proseStart > firstTree) {
        trailing = raw.slice(proseStart).trim();
    } else {
        // After checklist trees, next numbered section often restarts at "১."
        const lastBranch = branches[branches.length - 1] || '';
        const lastIdx = raw.lastIndexOf(lastBranch);
        if (lastIdx > firstTree) {
            const rest = raw.slice(lastIdx + lastBranch.length).trim();
            if (/^(?:[০-৯]|\d+)\.\s+/u.test(rest) || (rest.length > 20 && !/^\[/.test(rest) && !/^[┌├└┼]/.test(rest))) {
                trailing = rest;
            }
        }
    }

    return {
        type: 'tree',
        root,
        branches,
        inbound,
        lead: lead || null,
        trailing: trailing || null,
    };
}

function escapeRegExp(s) {
    return String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * Many→one merge diagrams:
 * WooCommerce DB ──┐
 * Meta Ads API ──┼──> [ETL] ➔ [Dashboard]
 * Courier APIs ──┘
 */
function parseMergeFlow(text) {
    const raw = String(text || '').trim();
    if (!/[┐┘]/.test(raw) || !/┼/.test(raw)) return null;

    const hubMatch = raw.match(/┼\s*─*>\s*\[([^\]]{2,80})\]/);
    if (!hubMatch) return null;

    const hub = hubMatch[1].trim();
    const afterHub = raw.slice(hubMatch.index + hubMatch[0].length);

    const cleanSource = (label) => {
        let s = String(label || '').trim();
        // Drop leading Bengali / prose before the English system name
        const latin = s.search(/[A-Za-z]/);
        if (latin > 0) s = s.slice(latin);
        s = s.replace(/^[:：\s]+/, '').trim();
        return s;
    };

    const sources = [];
    const sourceRe = /([^┐┘┼\[\]➔→\n]{2,80}?)\s*─+([┐┘┼])/g;
    let sm;
    while ((sm = sourceRe.exec(raw)) !== null) {
        const label = cleanSource(sm[1]);
        if (label.length >= 3 && label.length <= 60 && !sources.includes(label)) {
            sources.push(label);
        }
    }
    if (sources.length < 2) return null;

    const sinks = [];
    const sinkRe = /\[([^\]]{2,80})\]/g;
    let km;
    while ((km = sinkRe.exec(afterHub)) !== null) {
        const name = km[1].trim();
        if (name && name !== hub) sinks.push(name);
    }

    const firstSrcIdx = raw.indexOf(sources[0]);
    // Prefer prose before the diagram: last colon / Bangla sentence before first source
    let leadText = '';
    if (firstSrcIdx > 0) {
        leadText = raw.slice(0, firstSrcIdx).trim().replace(/[:：]$/, '');
        // If lead still contains junction leftovers, drop it
        if (/[┐┘┼]/.test(leadText)) leadText = '';
    }

    let trailing = null;
    const proseIdx = afterHub.search(/(?:ড্যাশবোর্ডের প্রধান|Dashboard features|১\.|1\.)/u);
    if (proseIdx >= 0) {
        trailing = afterHub.slice(proseIdx).trim();
    } else {
        const afterLast = afterHub.replace(/\[[^\]]*\]\s*/g, '').replace(/[➔→─\s]+/g, ' ').trim();
        if (afterLast.length > 16) trailing = afterLast;
    }

    return {
        type: 'mergeFlow',
        sources,
        hub,
        sinks,
        lead: leadText || null,
        trailing,
    };
}

/** Convert LaTeX math fragments into readable plain math (no KaTeX dependency). */
function latexToPlain(expr) {
    let s = String(expr || '');
    // Unwrap nested \text / \mathrm first
    for (let i = 0; i < 4; i += 1) {
        const next = s
            .replace(/\\text\{([^{}]*)\}/g, '$1')
            .replace(/\\mathrm\{([^{}]*)\}/g, '$1');
        if (next === s) break;
        s = next;
    }
    return s
        .replace(/\\&/g, '&')
        .replace(/\\times/g, '×')
        .replace(/\\cdot/g, '·')
        .replace(/\\left|\\right/g, '')
        .replace(/[{}]/g, '')
        .replace(/\s+/g, ' ')
        .trim();
}

function parseOneLatexFormula(inner) {
    const raw = String(inner || '').trim();
    const fracEq = raw.match(/^(.+?)\s*=\s*\\frac\{(.+)\}\{(.+)\}\s*$/);
    if (fracEq) {
        return {
            kind: 'frac',
            left: latexToPlain(fracEq[1]),
            num: latexToPlain(fracEq[2]),
            den: latexToPlain(fracEq[3]),
        };
    }
    const eq = raw.match(/^(.+?)\s*=\s*(.+)$/);
    if (eq) {
        return {
            kind: 'eq',
            left: latexToPlain(eq[1]),
            right: latexToPlain(eq[2]),
        };
    }
    return { kind: 'eq', left: '', right: latexToPlain(raw) };
}

/**
 * $$...$$ LaTeX blocks + optional TERM: definition rows that follow.
 */
function parseFormulas(text) {
    const raw = String(text || '');
    if (!/\$\$/.test(raw)) return null;

    const formulas = [];
    const re = /\$\$([\s\S]+?)\$\$/g;
    let m;
    let lastEnd = 0;
    let firstIdx = -1;
    while ((m = re.exec(raw)) !== null) {
        if (firstIdx < 0) firstIdx = m.index;
        formulas.push(parseOneLatexFormula(m[1]));
        lastEnd = m.index + m[0].length;
    }
    if (!formulas.length) return null;

    const lead = raw.slice(0, firstIdx).trim().replace(/[:：]$/, '');
    const after = raw.slice(lastEnd).trim();

    // Split "Name (gloss): definition।Next Name:" into KV rows
    const defs = [];
    let trailing = after;
    const defRe = /([A-Z][A-Za-z0-9][A-Za-z0-9 /:&+_-]{0,48}?(?:\s*\([^)]{1,48}\))?)\s*:\s*/g;
    const starts = [...after.matchAll(defRe)];
    if (starts.length >= 2) {
        for (let i = 0; i < starts.length; i += 1) {
            const key = starts[i][1].trim();
            const valStart = starts[i].index + starts[i][0].length;
            const valEnd = i + 1 < starts.length ? starts[i + 1].index : after.length;
            let value = after.slice(valStart, valEnd).trim();
            // Stop value before a following flow / subsection
            const cut = value.search(/\[[^\]]{2,40}\]\s*[➔→]|\$\$|(?:[০-৯]|\d{1,2})\.\d/);
            if (cut > 12) {
                trailing = value.slice(cut).trim() + (i + 1 < starts.length ? after.slice(starts[i + 1].index) : '');
                value = value.slice(0, cut).trim();
                defs.push({ key, value: value.replace(/[।.]\s*$/, '') });
                break;
            }
            value = value.replace(/[।.]\s*$/, '').trim();
            if (key && value) defs.push({ key, value });
            if (i === starts.length - 1) trailing = '';
        }
        if (!trailing && starts.length) {
            const last = starts[starts.length - 1];
            const lastVal = after.slice(last.index + last[0].length);
            const flowIdx = lastVal.search(/\[[^\]]{2,40}\]\s*[➔→]/);
            if (flowIdx >= 0) {
                const lastDef = defs[defs.length - 1];
                if (lastDef) {
                    lastDef.value = lastVal.slice(0, flowIdx).replace(/[।.]\s*$/, '').trim();
                }
                trailing = lastVal.slice(flowIdx).trim();
            }
        }
    }

    return {
        type: 'formulas',
        formulas,
        defs,
        lead: lead || null,
        trailing: trailing || null,
    };
}

function parseMessage(text) {
    const raw = String(text || '').trim();

    // Label on same line/chunk as quote
    const mid = raw.match(/((?:রিপিট পারচেজ মেসেজ টেমপ্লেট|VIP ওয়েলকাম মেসেজ|মেসেজ টেমপ্লেট|Message template|WhatsApp\/SMS)[^\n"“]{0,60}?)["“]([\s\S]+?)["”]/iu);
    if (mid) {
        const before = raw.slice(0, mid.index).trim();
        const after = raw.slice(mid.index + mid[0].length).trim();
        return {
            type: 'message-with-lead',
            lead: before || null,
            label: mid[1].replace(/[:：]\s*$/, '').trim(),
            text: mid[2].trim(),
            trailing: after || null,
        };
    }

    // Standalone quoted template (label may be previous line — still style as message)
    const plain = raw.match(/^["“]([\s\S]{40,}?)["”]\s*/u);
    if (plain && (/\[Customer Name\]|\[Product Name\]|কুপন কোড|VIPREORDER|1-Click|রি-অর্ডার/i.test(plain[1])
        || /আসসালামু|Congratulations|অভিনন্দন/.test(plain[1]))) {
        return {
            type: 'message-with-lead',
            lead: null,
            label: 'WhatsApp / SMS',
            text: plain[1].trim(),
            trailing: raw.slice(plain[0].length).trim() || null,
        };
    }
    return null;
}

function parseMatrixTable(text) {
    const raw = String(text || '');
    for (const spec of MATRIX_SPECS) {
        const idx = raw.indexOf(spec.headerMash);
        if (idx < 0) continue;

        const lead = raw.slice(0, idx).trim().replace(/[:：]\s*$/, '');
        let body = raw.slice(idx + spec.headerMash.length);
        let trailing = null;
        const cut = body.match(new RegExp(`(${SUB_ID_SRC}\\s+[\\s\\S]*)$`, 'u'));
        if (cut) {
            trailing = cut[1].trim();
            body = body.slice(0, cut.index).trim();
        }

        const anchors = spec.rows
            .map((key) => ({ key, idx: body.indexOf(key) }))
            .filter((x) => x.idx >= 0)
            .sort((a, b) => a.idx - b.idx);

        if (anchors.length < 2) continue;

        const rows = [];
        for (let i = 0; i < anchors.length; i += 1) {
            const start = anchors[i].idx + anchors[i].key.length;
            const end = i + 1 < anchors.length ? anchors[i + 1].idx : body.length;
            const blob = body.slice(start, end).trim();
            const { a, b } = splitTwoCells(blob);
            rows.push({
                feature: anchors[i].key,
                a,
                b,
            });
        }

        if (rows.length < 2) continue;

        return {
            type: 'compare',
            lead: lead || null,
            headers: spec.headers,
            rows,
            trailing,
        };
    }
    return null;
}

function splitTwoCells(blob) {
    const cellBlob = String(blob || '').trim();
    if (!cellBlob) return { a: '', b: '' };
    if (cellBlob.length < 8) return { a: cellBlob, b: '' };

    const patterns = [
        // GA4 event → params (exact event token at start)
        /^(view_item|add_to_cart|begin_checkout|purchase)(.+)$/u,
        // Pixel vs CAPI / dual-path mashed cells
        /^(.+?থেকে)((?:আপনার|Your).+)$/u,
        /^(.+?ট্র্যাকিং হয়)((?:[০-৯]|\d).+)$/u,
        /^(.+?\(\d+-\d+\/\d+\))((?:High|Low|Medium).+)$/iu,
        // KPI benchmark table cells
        /^(.+?(?:মূল্য|খরচ|অনুপাত|হার))((?:৳|<|AOV|[০-৯\d৩]).+)$/u,
        /^(.+?)((?:৳\s*[০-৯\d].+))$/u,
        /^(.+?)((?:৩:১|3:1|<).+)$/u,
        /^(.+?\sটি)(.+)$/u,
        /^(.+?(?:মিনিট|minutes?))(.+)$/iu,
        /^(.+?%\s*[–—\-]\s*[^%]+%\s*(?:ডাটা ট্র্যাকিং হয়)?)((?:[০-৯]|\d)+%\s*[–—\-].+)$/u,
        /^(.+?%\s*[–—\-]\s*[^%]+%)(.+)$/u,
        /^(.+?(?:জটিল|Complex|complex))(.+)$/u,
        /^(.+?(?:খুঁজতে হয়|required|needed))(.+)$/iu,
        // RFM / segment traits — longest match first
        /^(.+?(?:প্রোডাক্ট নেন|কেনাকাটা করেননি|কেনাকাটা করেন))((?:এক্সক্লুসিভ|রিপিট|বিশেষ|Early|Win-Back|Exclusive|রিপিট).+)$/u,
        /^(.+?(?:৳[\d,]+|[০-৯\d],?[০-৯\d]{0,3}০০০\+?\s*টাকা))(.+)$/u,
        /^(.+?(?:view_item|add_to_cart|begin_checkout|purchase))(.+)$/u,
        /^(.+?)(মাত্র\s.+)$/u,
        /^(.+?)((?:এক্সক্লুসিভ|রিপিট|বিশেষ|হোয়াটসঅ্যাপ|ডাইনামিক|প্রোডাক্ট কেয়ার|Early|Win-Back|Exclusive).+)$/u,
    ];

    for (const re of patterns) {
        const m = cellBlob.match(re);
        if (m && m[1].trim().length >= 4 && m[2].trim().length >= 4) {
            return { a: m[1].trim(), b: m[2].trim() };
        }
    }

    const mid = Math.floor(cellBlob.length / 2);
    let splitAt = mid;
    for (let j = Math.max(4, mid - 20); j <= Math.min(cellBlob.length - 4, mid + 24); j += 1) {
        const prev = cellBlob[j - 1];
        const next = cellBlob[j];
        if (/[০-৯0-9।)]$/.test(prev) && /[A-Za-zঅ-হা-য়]/.test(next)) {
            splitAt = j;
            break;
        }
    }
    return {
        a: cellBlob.slice(0, splitAt).trim(),
        b: cellBlob.slice(splitAt).trim(),
    };
}

function splitCompareCells(blob) {
    return splitTwoCells(blob);
}

function tryParseCompareTable(text) {
    const raw = String(text || '');
    if (!/বৈশিষ্ট্য|Feature|নিচের টেবিল|comparison table|Compare/i.test(raw)) {
        return null;
    }

    let body = raw;
    let lead = '';
    const bnIdx = raw.indexOf('বৈশিষ্ট্য');
    const enIdx = raw.toLowerCase().indexOf('feature');
    let featureIdx = -1;
    if (bnIdx >= 0 && enIdx >= 0) featureIdx = Math.min(bnIdx, enIdx);
    else featureIdx = Math.max(bnIdx, enIdx);
    if (featureIdx > 0) {
        lead = raw.slice(0, featureIdx).replace(/[:：]\s*$/, '').trim();
        body = raw.slice(featureIdx);
    }

    body = body.replace(/^বৈশিষ্ট্য|^Feature/i, '').trim();

    let headers = null;
    for (const [a, b] of COMPARE_HEADER_PAIRS) {
        const mashed = a + b;
        const pos = body.indexOf(mashed);
        if (pos === 0 || (pos >= 0 && pos < 8)) {
            headers = [a, b];
            body = body.slice(pos + mashed.length);
            break;
        }
        const spaced = `${a} ${b}`;
        const pos2 = body.indexOf(spaced);
        if (pos2 === 0 || (pos2 >= 0 && pos2 < 8)) {
            headers = [a, b];
            body = body.slice(pos2 + spaced.length);
            break;
        }
    }
    if (!headers) return null;

    const present = COMPARE_ROW_KEYS
        .map((key) => ({ key, idx: body.indexOf(key) }))
        .filter((x) => x.idx >= 0)
        .sort((a, b) => a.idx - b.idx);
    if (present.length < 2) return null;

    const rows = [];
    for (let i = 0; i < present.length; i += 1) {
        const start = present[i].idx + present[i].key.length;
        const end = i + 1 < present.length ? present[i + 1].idx : body.length;
        let cellBlob = body.slice(start, end).trim();
        cellBlob = cellBlob.replace(/(?:[০-৯]|\d+)\.\d+\s.*$/u, '').trim();
        const { a, b } = splitCompareCells(cellBlob);
        if (a || b) rows.push({ feature: present[i].key, a, b });
    }
    if (rows.length < 2) return null;

    return {
        type: 'compare',
        lead: lead || null,
        headers: ['বৈশিষ্ট্য / Feature', headers[0], headers[1]],
        rows,
    };
}

function expandParagraph(paragraph) {
    const out = [];

    // Subsection headers first so they aren't swallowed by flow/message parsers
    const sub = parseSubsectionTitle(paragraph);
    if (sub && (sub.rest.length > 10 || paragraph.length > 60)) {
        out.push({ type: 'subtitle', text: sub.title });
        if (sub.rest) out.push(...expandParagraph(sub.rest));
        return out;
    }

    const matrix = parseMatrixTable(paragraph);
    if (matrix) {
        if (matrix.lead) out.push(...expandParagraph(matrix.lead));
        out.push({ type: 'compare', headers: matrix.headers, rows: matrix.rows });
        if (matrix.trailing) out.push(...expandParagraph(matrix.trailing));
        return out;
    }

    const formulas = parseFormulas(paragraph);
    if (formulas) {
        if (formulas.lead) out.push(...expandParagraph(formulas.lead));
        out.push({ type: 'formulas', formulas: formulas.formulas });
        if (formulas.defs?.length) {
            out.push({ type: 'kvTable', rows: formulas.defs });
        }
        if (formulas.trailing) out.push(...expandParagraph(formulas.trailing));
        return out;
    }

    const merge = parseMergeFlow(paragraph);
    if (merge) {
        if (merge.lead) out.push(...expandParagraph(merge.lead));
        out.push({
            type: 'mergeFlow',
            sources: merge.sources,
            hub: merge.hub,
            sinks: merge.sinks,
        });
        if (merge.trailing) out.push(...expandParagraph(merge.trailing));
        return out;
    }

    const fork = parseForkDiagram(paragraph);
    if (fork) {
        if (fork.lead) out.push(...expandParagraph(fork.lead));
        out.push({ type: 'tree', root: fork.root, branches: fork.branches });
        if (fork.trailing) out.push(...expandParagraph(fork.trailing));
        return out;
    }

    const tree = parseTree(paragraph);
    if (tree) {
        if (tree.lead) out.push(...expandParagraph(tree.lead));
        out.push({
            type: 'tree',
            root: tree.root,
            branches: tree.branches,
            inbound: tree.inbound || null,
        });
        if (tree.trailing) out.push(...expandParagraph(tree.trailing));
        return out;
    }

    const compare = tryParseCompareTable(paragraph);
    if (compare) {
        if (compare.lead) out.push(...expandParagraph(compare.lead));
        out.push({ type: 'compare', headers: compare.headers, rows: compare.rows });
        return out;
    }

    const msgBundle = parseMessage(paragraph);
    if (msgBundle?.type === 'message-with-lead') {
        if (msgBundle.lead) out.push(...expandParagraph(msgBundle.lead));
        out.push({ type: 'message', label: msgBundle.label, text: msgBundle.text });
        if (msgBundle.trailing) out.push(...expandParagraph(msgBundle.trailing));
        return out;
    }

    const flow = parseFlow(paragraph);
    if (flow) {
        if (flow.lead) out.push(...expandParagraph(flow.lead));
        out.push({ type: 'flow', steps: flow.steps });
        if (flow.trailing) out.push(...expandParagraph(flow.trailing));
        return out;
    }

    for (const piece of splitOnNumberedMarkers(paragraph)) {
        out.push(...expandSimpleChunk(piece));
    }
    return out;
}

function expandSimpleChunk(piece) {
    const out = [];
    const numbered = parseNumberedRow(piece);
    if (numbered) {
        out.push({ type: 'numbered', ...numbered });
        return out;
    }
    const kv = parseKv(piece);
    if (kv && piece.length < 220) {
        out.push({ type: 'kv', ...kv });
        return out;
    }
    if (/^(?:[০-৯]|\d+)(?:\.\d+)+\s+\S/u.test(piece) && piece.length < 160 && !piece.includes('।')) {
        out.push({ type: 'subtitle', text: piece });
        return out;
    }
    out.push({ type: 'paragraph', text: piece });
    return out;
}

/**
 * @param {string[]} paragraphs
 * @param {Array<{src: string, alt?: string, caption?: string|null}>} [figures]
 * @returns {Array<Record<string, any>>}
 */
export function buildContentBlocks(paragraphs = [], figures = []) {
    // Join split paste fragments so ASCII trees / formulas / matrices aren't broken mid-token
    const joined = (paragraphs || [])
        .filter((p) => p && String(p).trim())
        .map((p) => String(p).trim())
        .join('');

    const atoms = [];
    if (joined) {
        for (const chunk of preSplitMegaText(joined)) {
            atoms.push(...expandParagraph(chunk));
        }
    }

    const blocks = [];
    let i = 0;
    while (i < atoms.length) {
        const atom = atoms[i];

        if (
            atom.type === 'compare'
            || atom.type === 'flow'
            || atom.type === 'message'
            || atom.type === 'subtitle'
            || atom.type === 'paragraph'
            || atom.type === 'tree'
            || atom.type === 'formulas'
            || atom.type === 'mergeFlow'
            || atom.type === 'kvTable'
        ) {
            blocks.push(atom);
            i += 1;
            continue;
        }

        if (atom.type === 'kv') {
            const rows = [];
            while (i < atoms.length && atoms[i].type === 'kv') {
                rows.push({ key: atoms[i].key, value: atoms[i].value });
                i += 1;
            }
            blocks.push({ type: 'kvTable', rows });
            continue;
        }

        if (atom.type === 'numbered') {
            const rows = [];
            while (i < atoms.length && atoms[i].type === 'numbered') {
                rows.push({ no: atoms[i].no, item: atoms[i].item, detail: atoms[i].detail });
                i += 1;
            }
            if (rows.length >= 2) {
                blocks.push({ type: 'numberedTable', rows });
            } else {
                blocks.push({
                    type: 'paragraph',
                    text: `${rows[0].no}. ${rows[0].item}${rows[0].detail ? `: ${rows[0].detail}` : ''}`,
                });
            }
            continue;
        }

        i += 1;
    }

    return injectFigureBlocks(blocks, figures);
}

/**
 * Place explanatory figures after the lead copy so they show in chapter preview.
 */
export function injectFigureBlocks(blocks = [], figures = []) {
    const list = Array.isArray(blocks) ? [...blocks] : [];
    const figs = (figures || [])
        .filter((f) => f && String(f.src || '').trim())
        .map((f) => ({
            type: 'figure',
            src: String(f.src).trim(),
            alt: String(f.alt || f.caption || 'Diagram').trim(),
            caption: f.caption ? String(f.caption).trim() : null,
        }));
    if (!figs.length) return list;

    let insertAt = 0;
    for (let i = 0; i < Math.min(list.length, 3); i += 1) {
        if (list[i]?.type === 'paragraph' || list[i]?.type === 'subtitle') {
            insertAt = i + 1;
        }
    }
    list.splice(insertAt, 0, ...figs);
    return list;
}

/**
 * Progressive disclosure — keep enough structure visible to scan.
 */
export function splitBlocksForPreview(blocks, previewLimit = 8) {
    const list = blocks || [];
    if (list.length <= previewLimit + 1) {
        return { preview: list, rest: [] };
    }

    let cut = previewLimit;
    for (let i = previewLimit; i < Math.min(list.length, previewLimit + 5); i += 1) {
        const t = list[i]?.type;
        if (t === 'compare' || t === 'kvTable' || t === 'numberedTable' || t === 'flow' || t === 'message' || t === 'tree' || t === 'formulas' || t === 'mergeFlow' || t === 'figure') {
            cut = i + 1;
            break;
        }
        if (t === 'subtitle') {
            cut = i;
            break;
        }
    }

    return {
        preview: list.slice(0, cut),
        rest: list.slice(cut),
    };
}
