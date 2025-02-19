import { format, parseISO } from "date-fns";
import { formatInTimeZone } from "date-fns-tz";


export function dateFormat(inputDate) {
    if (inputDate === null) {
        return "N/A";
    }

    // const utcDate = utcToZonedTime(parseISO(inputDate), "UTC");
    return formatInTimeZone(parseISO(inputDate), "UTC", "dd MMM yyyy, hh:mm a");
}