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
