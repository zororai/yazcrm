<?php

namespace Tests\Unit\DataCollection;

use App\Services\DataCollection\XlsForm\XlsFormParser;
use App\Services\DataCollection\XlsForm\XlsFormSamples;
use App\Support\DataCollection\SchemaWalker;
use PHPUnit\Framework\TestCase;

class XlsFormParserTest extends TestCase
{
    private function parse(array $survey, array $choices = [], array $settings = ['form_title' => 'T', 'form_id' => 't']): array
    {
        return (new XlsFormParser())->parse(XlsFormSamples::build($survey, $choices, $settings));
    }

    private function messages(array $result): array
    {
        return array_column($result['errors'], 'message');
    }

    public function test_household_sample_parses_cleanly(): void
    {
        $result = (new XlsFormParser())->parse(XlsFormSamples::householdSurvey());

        $this->assertSame([], $result['errors']);
        $schema = $result['schema'];
        $this->assertSame('Household Survey', $schema['title']);
        $this->assertSame('household_survey', $schema['id_string']);

        $byName = SchemaWalker::index($schema);
        $this->assertArrayNotHasKey('start', $byName, 'metadata rows are dropped');
        $this->assertSame('group', $byName['household']['type']);
        $this->assertSame('repeat', $byName['members']['type']);
        $this->assertSame('photo', $byName['house_photo']['type'], 'image is an alias of photo');
        $this->assertSame('select_one', $byName['ward']['type']);
        $this->assertSame('ward', $byName['ward']['list_name']);
        $this->assertCount(5, $byName['ward']['choices']);
        $this->assertSame('yes', $byName['respondent_name']['required']);
        $this->assertTrue($byName['respondent_name']['pii']);
        $this->assertSame("\${has_income} = 'yes'", $byName['income_sources']['relevant']);

        $fields = collect(SchemaWalker::fields($schema))->keyBy('name');
        $this->assertNull($fields['household_size']['repeat'], 'group children sit at the top level');
        $this->assertSame('members', $fields['member_age']['repeat']);
        $this->assertSame('members/member_age', $fields['member_age']['path']);
        $this->assertArrayNotHasKey('thanks', $fields->all(), 'notes hold no answer');
    }

    public function test_reports_duplicate_names_unknown_types_and_missing_lists(): void
    {
        $result = $this->parse([
            ['type' => 'text', 'name' => 'name', 'label' => 'Name'],
            ['type' => 'text', 'name' => 'name', 'label' => 'Name again'],
            ['type' => 'frobnicator', 'name' => 'x', 'label' => 'X'],
            ['type' => 'select_one colours', 'name' => 'colour', 'label' => 'Colour'],
            ['type' => 'select_one', 'name' => 'nolist', 'label' => 'No list'],
        ]);

        $errors = collect($result['errors']);
        $this->assertTrue($errors->contains(fn ($e) => $e['row'] === 3 && $e['column'] === 'name' && str_contains($e['message'], "Duplicate name 'name'")));
        $this->assertTrue($errors->contains(fn ($e) => $e['row'] === 4 && $e['column'] === 'type' && str_contains($e['message'], 'Unknown question type')));
        $this->assertTrue($errors->contains(fn ($e) => $e['row'] === 5 && str_contains($e['message'], "'colours', which is not on the choices sheet")));
        $this->assertTrue($errors->contains(fn ($e) => $e['row'] === 6 && str_contains($e['message'], 'has no choice list')));
        $this->assertSame('survey', $errors->first()['sheet']);
    }

    public function test_reports_unbalanced_groups_and_repeats(): void
    {
        $result = $this->parse([
            ['type' => 'begin group', 'name' => 'g', 'label' => 'G'],
            ['type' => 'text', 'name' => 'a', 'label' => 'A'],
            ['type' => 'end repeat'],
            ['type' => 'end group'],
            ['type' => 'begin repeat', 'name' => 'r', 'label' => 'R'],
            ['type' => 'text', 'name' => 'b', 'label' => 'B'],
        ]);

        $messages = $this->messages($result);
        $this->assertContains("\"end repeat\" closes the group 'g' opened on row 2.", $messages);
        $this->assertContains('"end group" has no matching "begin group".', $messages);
        $this->assertContains("\"begin repeat\" 'r' is never closed with \"end repeat\".", $messages);
    }

    public function test_reports_invalid_expressions_and_unknown_references(): void
    {
        $result = $this->parse([
            ['type' => 'integer', 'name' => 'age', 'label' => 'Age', 'constraint' => '. > 0 and'],
            ['type' => 'text', 'name' => 'school', 'label' => 'School', 'relevant' => '${agee} < 18'],
            ['type' => 'text', 'name' => 'job', 'label' => 'Job', 'relevant' => 'age >= 18'],
            ['type' => 'calculate', 'name' => 'calc'],
        ]);

        $errors = collect($result['errors'])->keyBy('row');
        $this->assertSame('constraint', $errors[2]['column']);
        $this->assertStringStartsWith('Invalid constraint expression', $errors[2]['message']);
        $this->assertSame('${agee} is not a field in this form.', $errors[3]['message']);
        $this->assertStringContainsString("refer to fields as \${age}", $errors[4]['message']);
        $this->assertSame("Calculate 'calc' has no calculation.", $errors[5]['message']);
    }

    public function test_choices_sheet_problems_and_translations(): void
    {
        $book = XlsFormSamples::build(
            [['type' => 'select_one yn', 'name' => 'q', 'label' => 'Q']],
            [
                ['list_name' => 'yn', 'name' => 'yes', 'label' => 'Yes'],
                ['list_name' => 'yn', 'name' => 'yes', 'label' => 'Yes again'],
                ['list_name' => 'yn', 'name' => 'no'],
            ],
            ['form_title' => 'T'],
        );
        // Add a Shona label column to the survey.
        $book->getSheetByName('survey')->setCellValue('Q1', 'label::Shona (sn)')->setCellValue('Q2', 'Mubvunzo');

        $result = (new XlsFormParser())->parse($book);

        $this->assertContains("Duplicate choice 'yes' in list 'yn'.", $this->messages($result));
        $this->assertContains("Choice 'no' in list 'yn' has no label.", $this->messages($result));
        $label = $result['schema']['children'][0]['label'];
        $this->assertSame(['default' => 'Q', 'Shona (sn)' => 'Mubvunzo'], $label);
    }

    public function test_malformed_file_is_reported_not_thrown(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xls');
        file_put_contents($path, "this is not a spreadsheet\x00\x01");

        try {
            $result = (new XlsFormParser())->parseFile($path);
        } finally {
            @unlink($path);
        }

        $this->assertNull($result['schema']);
        $this->assertSame('file', $result['errors'][0]['sheet']);
    }

    public function test_workbook_without_survey_sheet(): void
    {
        $book = XlsFormSamples::blank();
        $book->getSheetByName('survey')->setTitle('questions');

        $result = (new XlsFormParser())->parse($book);

        $this->assertSame('The workbook has no "survey" sheet.', $result['errors'][0]['message']);
    }
}
