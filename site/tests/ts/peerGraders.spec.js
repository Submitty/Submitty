import { describe, test, expect } from '@jest/globals';

describe('peer graders CSV processing', () => {
    test('parses and JSON-stringifies CSV with 2500 rows', () => {
        const header = 'student,grader\n';
        let csvContent = header;
        for (let i = 0; i < 2500; i++) {
            csvContent += `student_${i},grader_${i}\n`;
        }

        const lines = csvContent.split('\n');
        const headers = lines[0].split(',');
        let students_lines_index = -1;
        let graders_lines_index = -1;

        for (let k = 0; k < headers.length; k++) {
            if (headers[k].toLowerCase().trim() === 'student') {
                students_lines_index = k;
            }
            else if (headers[k].toLowerCase().trim() === 'grader') {
                graders_lines_index = k;
            }
        }

        expect(students_lines_index).toBe(0);
        expect(graders_lines_index).toBe(1);

        const jsonFile = [];
        for (let i = 1; i < lines.length; i++) {
            if (lines[i].trim() === '') {
                continue;
            }
            const built_line = {};
            const cells = lines[i].split(',');

            for (let j = 0; j < cells.length; j++) {
                if (cells[j].trim() !== '') {
                    built_line[headers[j].trim()] = cells[j].trim();
                }
            }
            jsonFile.push(built_line);
        }

        expect(jsonFile.length).toBe(2500);

        const serialized = JSON.stringify(jsonFile);
        expect(typeof serialized).toBe('string');

        const parsed = JSON.parse(serialized);
        expect(parsed.length).toBe(2500);
        expect(parsed[0]).toEqual({ student: 'student_0', grader: 'grader_0' });
        expect(parsed[2499]).toEqual({ student: 'student_2499', grader: 'grader_2499' });
    });
});
