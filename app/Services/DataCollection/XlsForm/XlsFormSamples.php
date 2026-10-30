<?php

namespace App\Services\DataCollection\XlsForm;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// The downloadable blank XLSForm and the sample household survey (also used
// by the seeder and the tests).
class XlsFormSamples
{
    public const SURVEY_COLUMNS = [
        'type', 'name', 'label', 'hint', 'required', 'required_message', 'relevant',
        'constraint', 'constraint_message', 'calculation', 'default', 'appearance',
        'choice_filter', 'repeat_count', 'read_only', 'pii',
    ];

    public static function blank(): Spreadsheet
    {
        return self::build([], [], ['form_title' => '', 'form_id' => '', 'version' => '']);
    }

    // Household survey: a group, a repeat (household members), skip logic,
    // constraints, a calculate, a geopoint, a photo and PII fields.
    public static function householdSurvey(): Spreadsheet
    {
        $survey = [
            ['type' => 'start', 'name' => 'start'],
            ['type' => 'end', 'name' => 'end'],
            ['type' => 'text', 'name' => 'respondent_name', 'label' => "Respondent's full name", 'required' => 'yes', 'pii' => 'yes'],
            ['type' => 'text', 'name' => 'respondent_phone', 'label' => 'Phone number', 'hint' => '10 digits, e.g. 0771234567',
                'constraint' => "regex(., '^0[0-9]{9}$')", 'constraint_message' => 'Enter a 10-digit phone number starting with 0.', 'pii' => 'yes'],
            ['type' => 'select_one gender', 'name' => 'respondent_gender', 'label' => 'Gender', 'required' => 'yes'],
            ['type' => 'integer', 'name' => 'respondent_age', 'label' => 'Age', 'required' => 'yes',
                'constraint' => '. >= 10 and . <= 100', 'constraint_message' => 'Age must be between 10 and 100.'],
            ['type' => 'select_one ward', 'name' => 'ward', 'label' => 'Ward', 'required' => 'yes'],
            ['type' => 'date', 'name' => 'interview_date', 'label' => 'Date of interview', 'required' => 'yes',
                'constraint' => '. <= today()', 'constraint_message' => 'The interview date cannot be in the future.'],
            ['type' => 'geopoint', 'name' => 'household_location', 'label' => 'Household location'],
            ['type' => 'image', 'name' => 'house_photo', 'label' => 'Photo of the house'],

            ['type' => 'begin group', 'name' => 'household', 'label' => 'Household'],
            ['type' => 'integer', 'name' => 'household_size', 'label' => 'How many people live in this household?', 'required' => 'yes',
                'constraint' => '. > 0 and . <= 30', 'constraint_message' => 'Enter between 1 and 30 people.'],
            ['type' => 'select_one yes_no', 'name' => 'has_income', 'label' => 'Does anyone in the household earn an income?', 'required' => 'yes'],
            ['type' => 'select_multiple income_source', 'name' => 'income_sources', 'label' => 'Sources of income',
                'required' => 'yes', 'relevant' => "\${has_income} = 'yes'"],
            ['type' => 'decimal', 'name' => 'monthly_income', 'label' => 'Total monthly income (USD)',
                'relevant' => "\${has_income} = 'yes'", 'constraint' => '. >= 0'],
            ['type' => 'end group'],

            ['type' => 'begin repeat', 'name' => 'members', 'label' => 'Household member'],
            ['type' => 'text', 'name' => 'member_name', 'label' => 'Name', 'required' => 'yes', 'pii' => 'yes'],
            ['type' => 'integer', 'name' => 'member_age', 'label' => 'Age', 'required' => 'yes',
                'constraint' => '. >= 0 and . <= 120', 'constraint_message' => 'Age must be between 0 and 120.'],
            ['type' => 'select_one gender', 'name' => 'member_gender', 'label' => 'Gender', 'required' => 'yes'],
            ['type' => 'select_one yes_no', 'name' => 'in_school', 'label' => 'Currently in school?', 'required' => 'yes',
                'relevant' => '${member_age} >= 5 and ${member_age} <= 24'],
            ['type' => 'end repeat'],

            ['type' => 'calculate', 'name' => 'member_count', 'calculation' => 'count(${members})'],
            ['type' => 'select_multiple needs', 'name' => 'needs', 'label' => 'Most urgent needs', 'required' => 'yes'],
            ['type' => 'text', 'name' => 'needs_other', 'label' => 'Other need (specify)', 'required' => 'yes',
                'relevant' => "selected(\${needs}, 'other')"],
            ['type' => 'note', 'name' => 'thanks', 'label' => 'Thank the respondent for their time.'],
        ];

        $choices = [];
        $lists = [
            'gender'        => ['female' => 'Female', 'male' => 'Male', 'other' => 'Other / prefer not to say'],
            'yes_no'        => ['yes' => 'Yes', 'no' => 'No'],
            'ward'          => ['ward_1' => 'Ward 1', 'ward_2' => 'Ward 2', 'ward_3' => 'Ward 3', 'ward_4' => 'Ward 4', 'ward_5' => 'Ward 5'],
            'income_source' => ['employment' => 'Formal employment', 'farming' => 'Farming', 'business' => 'Own business',
                'remittances' => 'Remittances', 'other' => 'Other'],
            'needs'         => ['food' => 'Food', 'school_fees' => 'School fees', 'health' => 'Health care',
                'shelter' => 'Shelter', 'skills' => 'Skills training', 'other' => 'Other'],
        ];
        foreach ($lists as $list => $items) {
            foreach ($items as $name => $label) {
                $choices[] = ['list_name' => $list, 'name' => $name, 'label' => $label];
            }
        }

        return self::build($survey, $choices, [
            'form_title' => 'Household Survey',
            'form_id'    => 'household_survey',
            'version'    => '2026103001',
        ]);
    }

    public static function build(array $survey, array $choices, array $settings): Spreadsheet
    {
        $book = new Spreadsheet();

        $sheet = $book->getActiveSheet()->setTitle('survey');
        self::fill($sheet, self::SURVEY_COLUMNS, $survey);

        self::fill($book->createSheet()->setTitle('choices'), ['list_name', 'name', 'label'], $choices);

        self::fill($book->createSheet()->setTitle('settings'), array_keys($settings), [$settings]);

        $book->setActiveSheetIndex(0);

        return $book;
    }

    public static function save(Spreadsheet $book, string $path): string
    {
        (new Xlsx($book))->save($path);

        return $path;
    }

    private static function fill($sheet, array $columns, array $rows): void
    {
        $sheet->fromArray($columns, null, 'A1');
        foreach ($rows as $i => $row) {
            $line = array_map(fn ($c) => $row[$c] ?? null, $columns);
            $sheet->fromArray($line, null, 'A'.($i + 2), true);
        }
        foreach (range(1, count($columns)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
        $sheet->getStyle('1:1')->getFont()->setBold(true);
    }
}
