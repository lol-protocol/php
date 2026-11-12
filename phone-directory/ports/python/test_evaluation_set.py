"""Runs the PHP parser's evaluation set (../../tests/fixtures/parser-evaluation.json) through this port.

Every case must come out exactly as written, except the ones listed in ../fallas-conocidas.txt, which must
keep failing: a change that fixes one removes it from the list, and one that breaks another fails here.
That way the ports cannot drift from the PHP parser without the drift showing up as a red test.
"""

import json
import os
import unittest

from phone_directory_parser import MultiLanguagePhoneDirectoryParser

HERE = os.path.dirname(os.path.abspath(__file__))
SET = os.path.join(HERE, '..', '..', 'tests', 'fixtures', 'parser-evaluation.json')
KNOWN_FAILURES = os.path.join(HERE, '..', 'fallas-conocidas.txt')


def known_failures():
    with open(KNOWN_FAILURES, encoding='utf-8') as f:
        return {line.strip() for line in f if line.strip() and not line.startswith('#')}


def passes(case):
    parser = MultiLanguagePhoneDirectoryParser()
    entries = parser.parse_content(case['entrada'], case['idioma'])
    actual = [{
        'tipo': e['type'],
        'nombre': e['person_name']['full_name'] if e['type'] == 'natural' else e['business_name'],
        'calle': e['location']['street'],
        'telefono': e['phone'],
    } for e in entries]
    expected_language = case.get('idioma_detectado')
    return actual == case['esperado'] and (expected_language is None or expected_language == parser.detected_language)


class EvaluationSetTest(unittest.TestCase):
    def test_every_case_behaves_as_the_list_says(self):
        with open(SET, encoding='utf-8') as f:
            cases = json.load(f)['casos']
        failures = known_failures()

        wrong = [c['id'] for c in cases if passes(c) == (c['id'] in failures)]

        self.assertEqual([], wrong, 'actualiza ports/fallas-conocidas.txt: estos casos no se comportan como dice')

    def test_the_list_only_names_cases_of_the_set(self):
        with open(SET, encoding='utf-8') as f:
            ids = {c['id'] for c in json.load(f)['casos']}

        self.assertEqual(set(), known_failures() - ids)


if __name__ == '__main__':
    unittest.main()
