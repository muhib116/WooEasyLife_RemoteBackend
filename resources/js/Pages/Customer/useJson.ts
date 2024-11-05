import { ref } from "vue"

const jsonData = {
    "items": [
        {
            name: 'Nasir',
            age: 24,
            designation: 'Frontend Eng',
            d: {
                "parameter_id": 10983,
                "value": 0
            }
        },
        ["reading", "<b>traveling</b>", "<i>swimming</i>"],
        {
            "title": "SD 1.1.1.1 Label 3 Overview",
            "description": "An overview of the SD 1.1.1.1 Label 3, detailing its current and future states along with the significance of these states.",
            "parameters": [
                {
                    "parameter_id": 10982,
                    "value": 0
                },
                {
                    "parameter_id": 10983,
                    "value": 0
                }
            ],
            "child": [
                {
                    "title": "Current State Analysis",
                    "description": "Analysis of the current state of SD 1.1.1.1 Label 3, indicating a very low value and the implications for performance and strategic direction.",
                    "parameters": [
                        {
                            "parameter_id": 10982,
                            "value": 0
                        }
                    ],
                    "child": []
                },
                {
                    "title": "Future State Vision",
                    "description": "Outline of the envisioned future state for SD 1.1.1.1 Label 3, highlighting the aspirations and potential improvements despite the current very low value.",
                    "parameters": [
                        {
                            "parameter_id": 10983,
                            "value": 0
                        }
                    ],
                    "child": []
                }
            ]
        },
        {
            "title": "Strategic Implications",
            "description": "Examining the strategic implications of the very low current and future states for SD 1.1.1.1 Label 3, and recommendations for addressing these challenges.",
            "parameters": [
                {
                    "parameter_id": 10982,
                    "value": 0
                },
                {
                    "parameter_id": 10983,
                    "value": 0
                }
            ],
            "child": []
        }
    ]
}

export const useJson = () => {
    const htmlText = ref('')

    function jsonToMarkdown(inputData) {
        let jsonData;
        try {
            if (typeof inputData === 'string') {
                // Attempt to parse JSON string
                jsonData = JSON.parse(inputData);
            } else {
                jsonData = inputData;
            }
        } catch (e) {
            // If parsing fails, treat as mixed content
            jsonData = inputData;
        }

        function convertToMarkdown(data, level = 0) {
            let markdown = '';

            if (typeof data === 'string') {
                // Convert HTML tags to Markdown
                data = data.replace(/<\/?(b|strong)>/gi, '**')
                    .replace(/<\/?(i|em)>/gi, '*')
                    .replace(/<h([1-6])>(.*?)<\/h\1>/gi, (_, hLevel, content) => `${'#'.repeat(parseInt(hLevel))} ${content}\n`)
                    .replace(/<ul>|<\/ul>/gi, '')
                    .replace(/<ol>|<\/ol>/gi, '')
                    .replace(/<li>(.*?)<\/li>/gi, (_, content) => `${'  '.repeat(level)}- ${content}\n`)
                    .replace(/<br\s*\/?>/gi, '\n')
                    .replace(/<a\s+href="(.*?)".*?>(.*?)<\/a>/gi, '[$2]($1)')
                    .replace(/<img\s+[^>]*src="(.*?)"[^>]*>/gi, '![]($1)')
                    .replace(/<[^>]+>/g, ''); // Remove other HTML tags
                markdown += data;
            } else if (Array.isArray(data)) {
                data.forEach((item) => {
                    markdown += `${'  '.repeat(level)}- ${convertToMarkdown(item, level + 1)}\n`;
                });
            } else if (typeof data === 'object' && data !== null) {
                for (const key in data) {
                    if (data[key] !== undefined && data[key] !== null) {
                        markdown += `${'  '.repeat(level)}- **${key}**: ${convertToMarkdown(data[key], level + 1)}\n`;
                    }
                }
            } else {
                markdown += `${data}`;
            }

            return markdown.trim();
        }

        return convertToMarkdown(jsonData);
    }

    htmlText.value = jsonToMarkdown(jsonData)

    return {
        htmlText,
    }
}