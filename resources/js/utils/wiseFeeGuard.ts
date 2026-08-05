/**
 * Client-side mirror of KnowledgeSeedValidator::answerFactGuards.
 * Uses Unicode Nd (\p{Nd}) so Bangla digits (৬০) match PHP \d under /u.
 * Bare digits / percents need refuse phrasing in the SAME sentence.
 */
export function feeInvented(text: string): boolean {
    const t = (text || "").trim();
    if (!t) {
        return false;
    }

    if (/(?:৳|tk\.?|taka|bdt|টাকা)\s*\p{Nd}|\p{Nd}+\s*(?:৳|tk\.?|taka|bdt|টাকা)/iu.test(t)) {
        return true;
    }
    if (/(?:\+?88)?01[3-9]\p{Nd}{8}/u.test(t)) {
        return true;
    }

    const refuse = /অনুমান|আন্দাজ|দিব না|বলব না|বানাব না|invent/iu;
    const sentences = t.split(/(?<=[।!?.\n])\s*/u).map((s) => s.trim()).filter(Boolean);
    const parts = sentences.length > 0 ? sentences : [t];

    for (const sentence of parts) {
        const hasRefuse = refuse.test(sentence);
        if (/(?<!\p{Nd})\p{Nd}{2,}(?!\p{Nd})/u.test(sentence) && !hasRefuse) {
            return true;
        }
        if (/\p{Nd}+\s*%/u.test(sentence) && !hasRefuse) {
            return true;
        }
    }

    return false;
}
