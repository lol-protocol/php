import os
import tempfile
import unittest

from phone_directory_parser import (
    GeoLocation,
    MultiLanguagePhoneDirectoryParser,
    PersonName,
    PhoneDirectoryManager,
)


class PersonNameTest(unittest.TestCase):
    def test_comma_format_puts_surname_before_the_comma(self):
        self.assertEqual('García, Juan José', PersonName('GARCÍA, Juan José').formatted())

    def test_last_word_is_the_surname_by_default(self):
        self.assertEqual('Smith, John Michael', PersonName('John Michael Smith').formatted())

    def test_spanish_takes_two_surnames(self):
        self.assertEqual(['Valdez', 'Ruiz'], PersonName('Juan Valdez Ruiz', 'es').last_names)

    def test_particles_stay_with_the_surname(self):
        self.assertEqual('de la Cruz, María', PersonName('María de la Cruz').formatted())

    def test_empty_name_is_rejected(self):
        with self.assertRaises(ValueError):
            PersonName('   ')


class GeoLocationTest(unittest.TestCase):
    def test_country_code_must_be_two_letters(self):
        with self.assertRaises(ValueError):
            GeoLocation('1A', 'Main Street')


class ParserTest(unittest.TestCase):
    def test_spanish_entry_is_detected_and_parsed(self):
        parser = MultiLanguagePhoneDirectoryParser()
        entries = parser.parse_content("García, Juan José\nCalle Principal 123\n555-1234567")

        self.assertEqual('es', parser.detected_language)
        self.assertEqual([], parser.errors)
        self.assertEqual('Calle Principal 123', entries[0]['location']['street'])

    def test_street_words_only_match_whole_words(self):
        # "Kristen" contains "st" and "Olivia" contains "via"; neither is a street.
        entries = MultiLanguagePhoneDirectoryParser().parse_content("Kristen Olivia\n12 Oak Avenue", 'en')

        self.assertEqual('Olivia, Kristen', entries[0]['person_name']['formatted'])

    def test_german_compound_street_is_recognized(self):
        entries = MultiLanguagePhoneDirectoryParser().parse_content("Hans Müller\nHauptstraße 5")

        self.assertEqual('Hauptstraße 5', entries[0]['location']['street'])

    def test_street_without_street_word_keeps_accents_and_hyphens(self):
        entries = MultiLanguagePhoneDirectoryParser().parse_content("Hélène Lefèvre\n8 Clos Saint-Éloi", 'fr')

        self.assertEqual('8 Clos Saint-Éloi', entries[0]['location']['street'])

    def test_number_inside_a_name_is_not_a_street(self):
        entries = MultiLanguagePhoneDirectoryParser().parse_content("Restaurante 3 Hermanos\nCalle Larga 5", 'es')

        self.assertEqual(1, len(entries))
        self.assertEqual('Calle Larga 5', entries[0]['location']['street'])

    def test_street_with_number_last_and_no_street_word(self):
        entries = MultiLanguagePhoneDirectoryParser().parse_content("COSTA, Rui\nTravessa do Carmo 5", 'pt')

        self.assertEqual(1, len(entries))
        self.assertEqual('COSTA, Rui', entries[0]['person_name']['full_name'])
        self.assertEqual('Travessa do Carmo 5', entries[0]['location']['street'])

    def test_dwelling_line_is_the_street_after_the_name(self):
        entries = MultiLanguagePhoneDirectoryParser().parse_content("TAYLOR, Jim\nApt 4 Building 7\n555-123-4567", 'en')

        self.assertEqual(1, len(entries))
        self.assertEqual('Apt 4 Building 7', entries[0]['location']['street'])

    def test_name_ending_in_a_number_stays_a_name(self):
        entries = MultiLanguagePhoneDirectoryParser().parse_content("Restaurante Siglo 21\nCalle Larga 5", 'es')

        self.assertEqual(1, len(entries))
        self.assertEqual('Calle Larga 5', entries[0]['location']['street'])

    def test_records_without_blank_line_between_them(self):
        entries = MultiLanguagePhoneDirectoryParser().parse_content(
            "HALL, Ruth\n3 Main Street\n555-123-4567\nLEE, Ken\n8 Oak Lane\n555-987-6543", 'en')

        self.assertEqual(['3 Main Street', '8 Oak Lane'], [e['location']['street'] for e in entries])

    def test_city_line_does_not_split_a_record(self):
        entries = MultiLanguagePhoneDirectoryParser().parse_content("SMITH, John\n12 Oak Street\nSpringfield\n555-123-4567", 'en')

        self.assertEqual(1, len(entries))
        self.assertEqual('555-123-4567', entries[0]['phone'])

    def test_via_with_number_last_is_italian(self):
        parser = MultiLanguagePhoneDirectoryParser()

        self.assertEqual('it', parser.detect_language("ROSSI, Luigi\nVia Roma 10"))
        self.assertEqual('en', parser.detect_language("SMITH, John\n12 Via Lane"))

    def test_a_business_is_its_own_kind_of_entry(self):
        entries = MultiLanguagePhoneDirectoryParser().parse_content("ACME Corp\n10 Main Street\n555-123-4567", 'en')

        self.assertEqual('juridical', entries[0]['type'])
        self.assertEqual('ACME Corp', entries[0]['business_name'])
        self.assertEqual('corp', entries[0]['business_type'])
        self.assertNotIn('person_name', entries[0])

    def test_a_surname_that_is_a_business_word_is_still_a_person(self):
        entries = MultiLanguagePhoneDirectoryParser().parse_content("BANK, Henry\n3 Oak Road", 'en')

        self.assertEqual('natural', entries[0]['type'])

    def test_one_record_per_line_with_and_without_separators(self):
        entries = MultiLanguagePhoneDirectoryParser().parse_content(
            "SMITH John 12 Oak St ........ 555-111-2222\nGARCÍA LÓPEZ Juan Calle Mayor 12 555-123-4567", 'es')

        self.assertEqual(2, len(entries))
        self.assertEqual('GARCÍA LÓPEZ, Juan', entries[1]['person_name']['full_name'])
        self.assertEqual('Calle Mayor 12', entries[1]['location']['street'])

    def test_dr_opening_a_name_is_not_a_street(self):
        entries = MultiLanguagePhoneDirectoryParser().parse_content("Dr. SMITH, John\n12 Oak Street", 'en')

        self.assertEqual('Dr. SMITH, John', entries[0]['person_name']['full_name'])

    def test_a_street_without_a_street_word_gives_way_to_one_with_it(self):
        entries = MultiLanguagePhoneDirectoryParser().parse_content("Luis Sur\nDivisión 2\nCalle Mayor 5", 'es')

        self.assertEqual('Calle Mayor 5', entries[0]['location']['street'])

    def test_separator_lines_split_entries(self):
        entries = MultiLanguagePhoneDirectoryParser().parse_content("Ann Jones\n5 Pine Rd\n- - -\nBob Lee\n7 Oak St")

        self.assertEqual(2, len(entries))

    def test_error_line_numbers_point_at_the_original_line(self):
        parser = MultiLanguagePhoneDirectoryParser()
        parser.parse_content("\n\nNo Street Here\n")

        self.assertEqual(3, parser.errors[0]['line'])

    def test_country_code_is_applied_to_entries(self):
        entries = MultiLanguagePhoneDirectoryParser('mx').parse_content("Juan Pérez\nCalle Hidalgo 4")

        self.assertEqual('MX', entries[0]['location']['country_code'])


class PhoneFormatsTest(unittest.TestCase):
    P = MultiLanguagePhoneDirectoryParser

    def test_each_language_reads_its_own_countries_formats(self):
        cases = [('fr', 'Tel 01 42 68 53 00', '01 42 68 53 00'), ('fr', '+33 1 42 68 53 00', '+33 1 42 68 53 00'),
                 ('de', '(089) 654321', '(089) 654321'), ('de', '0711/12345', '0711/12345'),
                 ('it', '06 1234567', '06 1234567'), ('pt', '21 342 0000', '21 342 0000'),
                 ('es', '91 234 56 78', '91 234 56 78'), ('es', '2-345-678', '2-345-678')]
        for language, line, expected in cases:
            self.assertEqual(expected, self.P().phone_in(line, language), (language, line))

    def test_a_looser_format_is_not_read_in_another_languages_directory(self):
        self.assertIsNone(self.P().phone_in('Tel 01 42 68 53 00', 'en'))
        self.assertIsNone(self.P().phone_in('Tel 01 42 68 53 00', None))

    def test_a_phone_never_starts_or_ends_next_to_another_digit_group(self):
        self.assertEqual('555-123-4567', self.P().phone_in('Calle Mayor 12 555-123-4567', 'es'))
        self.assertEqual('030 123456', self.P().phone_in('Hauptstraße 5 030 123456', 'de'))

    def test_the_named_exchange_form(self):
        self.assertEqual('BUtterfield 8-4521', self.P().phone_in('BUtterfield 8-4521', 'en'))

    def test_ocr_misreadings_inside_a_phone_are_repaired(self):
        self.assertEqual('555-123-4567', self.P().repair_ocr_digits('555-l23-4567'))
        self.assertEqual('555-123-4567', self.P().phone_in('555-l23-4567', 'en'))
        self.assertEqual('Oil BUtterfield 8-4521', self.P().repair_ocr_digits('Oil BUtterfield 8-4521'))

    def test_the_phone_can_share_the_line_with_the_name(self):
        entries = MultiLanguagePhoneDirectoryParser().parse_content("SMITH, John ........ 555-123-4567\n12 Main Street")

        self.assertEqual('555-123-4567', entries[0]['phone'])
        self.assertEqual('Smith, John', entries[0]['person_name']['formatted'])

    def test_text_left_beside_a_phone_with_a_digit_is_not_a_name(self):
        self.assertIsNone(self.P.name_beside_phone('555-123-4567 ext. 12', '555-123-4567'))


class ManagerTest(unittest.TestCase):
    def test_process_file_stores_and_finds_entries(self):
        workdir = tempfile.mkdtemp()
        path = os.path.join(workdir, 'dir.txt')
        with open(path, 'w', encoding='utf-8') as f:
            f.write("García, Juan José\nCalle Principal 123\n\nSmith, John\n123 Main Street\n")

        manager = PhoneDirectoryManager(os.path.join(workdir, 'test.db'))
        try:
            result = manager.process_file(path)

            self.assertEqual(2, result['inserted'])
            self.assertEqual(1, len(manager.find_by_last_name('García')))
        finally:
            manager.close()


if __name__ == '__main__':
    unittest.main()
