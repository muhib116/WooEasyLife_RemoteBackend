import { format } from "date-fns";


export function dateFormat(inputDate) {
    if (inputDate === null) {
        return "N/A";
    }

    return format(new Date(inputDate), "PPp"); // Example: Jan 18, 2025, 12:00 AM
}