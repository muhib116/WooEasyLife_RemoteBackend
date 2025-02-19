import { format, parseISO } from "date-fns";


export function dateFormat(inputDate) {
    if (inputDate === null) {
        return "N/A";
    }

    return format(parseISO(inputDate), "dd MMM yyyy, hh:mm a"); // Example: Jan 18, 2025, 12:00 AM
}