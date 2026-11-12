#!/usr/bin/env python3
"""
Phone Directory Parser - Python Implementation
Genealogical phone directory parsing with multi-language support
"""

import re
import sqlite3
from datetime import datetime
from typing import List, Dict, Optional, Tuple
from dataclasses import dataclass


@dataclass
class PersonName:
    """Parse and decompose personal names into components (same rules as the PHP PersonName)."""

    PARTICLES = ['de', 'del', 'della', 'di', 'da', 'das', 'do', 'dos', 'du',
                 'van', 'von', 'der', 'den', 'le', 'la', 'los', 'las']

    # Single-letter connectors collide with middle initials ("John E Smith"), so they only count when the language uses them.
    LANGUAGE_CONNECTORS = {'es': ['y'], 'pt': ['e'], 'it': ['e']}

    LANGUAGE_SURNAME_COUNT = {'es': 2, 'pt': 2}

    full_name: str
    language: Optional[str] = None
    first_names: List[str] = None
    last_names: List[str] = None

    def __post_init__(self):
        if not self.full_name or not self.full_name.strip():
            raise ValueError("Full name cannot be empty")

        self._parse_name()

    def _is_particle(self, word: str) -> bool:
        lower = word.lower()
        return lower in self.PARTICLES or lower in self.LANGUAGE_CONNECTORS.get(self.language, [])

    def _normalize_case(self, word: str, is_leading: bool = False) -> str:
        if not is_leading and self._is_particle(word):
            return word.lower()
        return word.lower().title()

    def _parse_name(self):
        """Parse "LAST, First" or "First Last" into first and last names."""
        full_name = self.full_name.strip()

        if ',' in full_name:
            last_part, first_part = full_name.split(',', 1)
            self.last_names = [self._normalize_case(w) for w in last_part.split()]
            self.first_names = [self._normalize_case(w, i == 0) for i, w in enumerate(first_part.split())]
            return

        parts = [self._normalize_case(w, i == 0) for i, w in enumerate(full_name.split())]

        # Walk backwards taking one surname per iteration, pulling in any particles that precede it
        # ("de la Cruz", "van der Rohe"); the first token always stays a given name.
        surname_count = self.LANGUAGE_SURNAME_COUNT.get(self.language, 1)
        last_names = []
        taken = 0
        while taken < surname_count and len(parts) > 1:
            group = [parts.pop()]
            while len(parts) > 1 and self._is_particle(parts[-1]):
                group.insert(0, parts.pop())
            last_names = group + last_names
            taken += 1

        self.first_names = parts
        self.last_names = last_names

    @property
    def primary_last_name(self) -> str:
        return self.last_names[0] if self.last_names else ""

    def formatted(self) -> str:
        """Return formatted name as LASTNAME, Firstname Middle."""
        if not self.last_names:
            return ' '.join(self.first_names)

        last_str = ' '.join(self.last_names)
        first_str = ' '.join(self.first_names)
        return f"{last_str}, {first_str}"

    def to_dict(self) -> Dict:
        return {
            'full_name': self.full_name,
            'first_names': self.first_names,
            'last_names': self.last_names,
            'primary_last_name': self.primary_last_name,
            'formatted': self.formatted()
        }


@dataclass
class GeoLocation:
    """Geographic location with country, zone, city, street."""

    country_code: str
    street: str
    zone: Optional[str] = None
    city: Optional[str] = None

    def __post_init__(self):
        if not re.fullmatch(r'[A-Za-z]{2}', self.country_code):
            raise ValueError("Country code must be 2 letters (ISO 3166-1)")
        self.country_code = self.country_code.upper()

    def full_address(self) -> str:
        """Return full formatted address."""
        parts = [self.street]
        if self.city:
            parts.append(self.city)
        if self.zone:
            parts.append(self.zone)
        parts.append(self.country_code)
        return ', '.join(parts)

    def to_dict(self) -> Dict:
        return {
            'country_code': self.country_code,
            'zone': self.zone,
            'city': self.city,
            'street': self.street,
            'full_address': self.full_address()
        }


class MultiLanguagePhoneDirectoryParser:
    """Parse phone directories in multiple languages."""

    STREET_MARKERS = {
        'es': ['calle', 'c/', 'avenida', 'av', 'plaza', 'pasaje', 'camino', 'ruta', 'carrera'],
        'en': ['street', 'st', 'avenue', 'ave', 'road', 'rd', 'drive', 'dr', 'lane', 'ln',
               'boulevard', 'blvd', 'circle', 'cir'],
        'fr': ['rue', 'avenue', 'allée', 'place', 'boulevard', 'bd', 'cours', 'square'],
        'pt': ['rua', 'avenida', 'av', 'praça', 'alameda', 'estrada', 'largo'],
        'de': ['ring', 'hof'],
        'it': ['via', 'viale', 'corso', 'piazza', 'largo', 'strada'],
    }

    # German compounds glue the street type onto the name ("Hauptstraße"), so these match as word endings.
    STREET_SUFFIXES = {'de': ['straße', 'strasse', 'allee', 'weg', 'platz']}

    # Only vocabulary distinctive enough to override English, the baseline language; words that are also
    # English ("plaza", "avenue", "via") would otherwise misdetect an English directory.
    DETECTION_MARKERS = {
        'es': ['calle', 'avenida', 'pasaje', 'camino', 'ruta', 'carrera'],
        'fr': ['rue', 'allée', 'cours'],
        'pt': ['rua', 'avenida', 'praça', 'alameda', 'estrada', 'largo'],
        'de': [],
        'it': ['viale', 'corso', 'piazza', 'largo', 'strada'],
    }

    SEPARATOR_PATTERN = re.compile(r'^(?:[-=_*]\s*){2,}$')

    # Same as PhonePattern::REGEX: digit groups (555-123-4567, 5551234567) and the named exchange of early
    # directories ("BUtterfield 8-4521"), which needs a separator on both sides of the word.
    PHONE_PATTERN = r'\b[A-Za-z]{3,}[ .-]\d[ -]\d{4}\b|\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b|\b\d{10}\b'

    # Same as PhonePattern::LANGUAGE_FORMATS: the formats of each language's countries, kept per language
    # because the looser ones would read a house number as part of a phone in another language's directory.
    LANGUAGE_PHONE_FORMATS = {
        'fr': [r'(?:\+33[ .]?[1-9]|0[1-9])(?:[ .]?\d{2}){4}'],
        'de': [r'(?:\+49[ ]?[1-9]\d{1,3}|\(0\d{2,4}\)|0\d{2,4})[ /-]?\d{3,8}(?:-\d{1,4})?'],
        'it': [r'(?:\+39[ ]?)?0\d{1,3}[ /-]?\d{4,8}'],
        'pt': [r'(?:\+351[ ]?)?(?:[2-9]\d[ ]\d{3}[ ]\d{4}|[2-9]\d{2}[ ]\d{3}[ ]\d{3})'],
        'es': [r'(?:\+34[ ]?)?[6-9]\d[ ]\d{3}(?:[ ]\d{2}){2}',
               r'(?:\(\d{1,3}\)[ ]?|\d{1,3}[ -])\d{3}[ -]\d{3,4}'],
    }
    # Not preceded by a digit or a "+", not followed by a separator and a digit.
    _PHONE_BEFORE = r'(?<![\d+])'
    _PHONE_AFTER = r'(?![ ./-]?\d)'
    _phone_regexes = {}

    @classmethod
    def phone_regex(cls, language: Optional[str] = None) -> 're.Pattern':
        """The base formats plus those of `language`'s countries (English and unknown languages: the base)."""
        key = language if language in cls.LANGUAGE_PHONE_FORMATS else ''
        if key not in cls._phone_regexes:
            parts = [cls.PHONE_PATTERN] + [cls._PHONE_BEFORE + '(?:' + f + ')' + cls._PHONE_AFTER
                                           for f in cls.LANGUAGE_PHONE_FORMATS.get(key, [])]
            cls._phone_regexes[key] = re.compile('|'.join(parts))
        return cls._phone_regexes[key]

    _OCR_CHEAP_TEST = re.compile(r'\d[ .\-]?[OolI]|[OolI][ .\-]?\d')
    _OCR_RUN = re.compile(r'(?<![^\W_])[\dOolI](?:[ .\-]?[\dOolI])+(?![^\W_])')

    @classmethod
    def repair_ocr_digits(cls, line: str) -> str:
        """Same as PhonePattern::repairOcrDigits: "l" or "I" for 1 and "O" or "o" for 0 inside what looks like a
        phone number ("555-l23-4567" gives "555-123-4567"), only in a run with at least 6 real digits and at
        most 2 such letters, so words ("Oil") and exchange names ("BUtterfield 8-4521") are left alone."""
        if not cls._OCR_CHEAP_TEST.search(line):
            return line

        def fix(m):
            digits = len(re.findall(r'\d', m.group()))
            letters = len(re.findall(r'[OolI]', m.group()))
            return m.group().translate(str.maketrans('OolI', '0011')) if 0 < letters <= 2 and digits >= 6 else m.group()
        return cls._OCR_RUN.sub(fix, line)

    def phone_in(self, line: str, language: Optional[str] = None) -> Optional[str]:
        """The line's phone, also when the scan misread a digit as a letter ("555-l23-4567"); None if it has none."""
        regex = self.phone_regex(language)
        match = regex.search(line)
        if match:
            return match.group()
        repaired = self.repair_ocr_digits(line)
        match = regex.search(repaired) if repaired != line else None
        return match.group() if match else None

    @staticmethod
    def name_beside_phone(line: str, phone: str) -> Optional[str]:
        """What a line says besides its phone, when that could be a name: "SMITH, John ........ 555-123-4567"
        gives "SMITH, John". None when nothing is left or what is left has a digit ("555-123-4567 ext. 12")."""
        offset = line.find(phone)
        if offset < 0:
            return None
        rest = (line[:offset] + ' ' + line[offset + len(phone):]).strip(' \t.,:;-\u2013\u2014')
        return rest if re.search(r'[^\W\d_]', rest) and not re.search(r'\d', rest) else None

    def __init__(self, country_code: str = 'US'):
        self.country_code = country_code.upper()
        self.entries = []
        self.errors = []
        self.detected_language = 'en'

    @staticmethod
    def _words_pattern(words: List[str], suffixes: List[str]) -> Optional['re.Pattern']:
        parts = []
        if words:
            parts.append(r'\b(?:' + '|'.join(re.escape(w) for w in words) + r')\b')
        parts.extend(re.escape(s) + r'\b' for s in suffixes)
        return re.compile('|'.join(parts), re.IGNORECASE) if parts else None

    # Same as the PHP LANGUAGE_DETECTION_LINES: "Via Roma 10" opens the line and ends with the number,
    # a shape English doesn't use ("12 Via Lane"), so "via" counts as Italian only there.
    DETECTION_LINES = {
        'it': re.compile(r"^[ \t]*via\s+[^\W\d_](?:[^\W\d_]|['’.\-])*(?:\s+[^\W\d_](?:[^\W\d_]|['’.\-])*)*,?\s+\d{1,5}[a-z]?\b",
                         re.IGNORECASE | re.MULTILINE),
    }

    def detect_language(self, content: str) -> str:
        """Auto-detect language from whole-word street vocabulary; defaults to English."""
        scores = {'en': 0}
        for lang, words in self.DETECTION_MARKERS.items():
            pattern = self._words_pattern(words, self.STREET_SUFFIXES.get(lang, []))
            scores[lang] = len(pattern.findall(content)) if pattern else 0
            if lang in self.DETECTION_LINES:
                scores[lang] += len(self.DETECTION_LINES[lang].findall(content))

        best = max(scores, key=scores.get)
        return best if scores[best] > 0 else 'en'

    def parse_file(self, filepath: str, language: Optional[str] = None) -> List[Dict]:
        """Parse phone directory file."""
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()

        return self.parse_content(content, language)

    def parse_content(self, content: str, language: Optional[str] = None) -> List[Dict]:
        """Parse phone directory content."""
        if not language:
            language = self.detect_language(content)

        self.detected_language = language
        self.entries = []
        self.errors = []

        buffer = []
        buffer_start = 0

        # Line numbers are 1-based positions in the original content, so errors point at the real line.
        for line_number, line in enumerate(content.split('\n'), start=1):
            line = line.strip()

            if not line or self.SEPARATOR_PATTERN.match(line):
                if buffer:
                    self._process_buffer(buffer, buffer_start, language)
                    buffer = []
                continue

            # Most historical directories print one full record per line; try that shape first.
            single = self._split_single_line(line, language)
            if single is not None:
                if buffer:
                    self._process_buffer(buffer, buffer_start, language)
                    buffer = []
                name, street, phone = single
                self._finalize(name, street, phone, self._business_type_of_name(name, language), line_number, language)
                continue

            if not buffer:
                buffer_start = line_number
            buffer.append(line)

        if buffer:
            self._process_buffer(buffer, buffer_start, language)

        return self.entries

    # ---- Street rules: same as the PHP StreetRules and StreetFallback ----

    # Languages that write the house number after the street ("Travessa do Carmo 5").
    NUMBER_LAST_LANGUAGES = ('es', 'pt', 'it', 'de')

    # English street words that also open a name ("Dr. SMITH, John", "St. Mary Lodge"): they only make a
    # street when the line has a digit or they are not its first word.
    NAME_ABBREVIATIONS = {'en': ['dr', 'st']}

    _WORD = r"[^\W\d_](?:[^\W\d_]|['’.\-])*"
    _NUMBER_FIRST = re.compile(rf"\d{{1,5}}[A-Za-z]?\s+{_WORD}(?:\s+{_WORD})*")
    _NUMBER_LAST = re.compile(rf"{_WORD}(?:\s+{_WORD})*\s+\d{{1,5}}[A-Za-z]?")
    DWELLING = re.compile(r"(?:apt|apartment|unit|flat|suite|ste|bldg|building)\.?\s*#?\d{1,5}[a-z]?"
                          r"(?:\s+[^\W_](?:[^\W_]|['’.\-#])*)*", re.IGNORECASE)

    _street_patterns = {}

    @classmethod
    def _bounded(cls, words: List[str]) -> str:
        # Not \b: "c/" ends in a slash, and \b only sees a word edge after a letter or digit.
        return r'(?<![^\W_])(?:' + '|'.join(re.escape(w) for w in words) + r')(?![^\W_])'

    @classmethod
    def _street_pattern(cls, language: str) -> 're.Pattern':
        """The street words of `language`; the abbreviations of NAME_ABBREVIATIONS are not in it."""
        if language not in cls._street_patterns:
            markers = cls.STREET_MARKERS.get(language, cls.STREET_MARKERS['en'])
            abbreviations = cls.NAME_ABBREVIATIONS.get(language, [])
            parts = [cls._bounded([m for m in markers if m not in abbreviations])]
            parts.extend('(?:' + re.escape(s) + r')\b' for s in cls.STREET_SUFFIXES.get(language, []))
            cls._street_patterns[language] = re.compile('|'.join(parts), re.IGNORECASE)
        return cls._street_patterns[language]

    def _has_street_word(self, line: str, language: str) -> bool:
        if self._street_pattern(language).search(line):
            return True
        abbreviations = self.NAME_ABBREVIATIONS.get(language)
        if not abbreviations:
            return False
        matches = list(re.finditer(self._bounded(abbreviations), line, re.IGNORECASE))
        if not matches:
            return False
        return bool(re.search(r'\d', line)) or any(line[:m.start()].strip() for m in matches)

    def _is_only_a_phone(self, text: str, language: str) -> bool:
        trimmed = text.strip()
        match = self.phone_regex(language).search(trimmed) if trimmed else None
        return bool(match) and match.group() == trimmed

    def _extract_street(self, line: str, language: str) -> Optional[str]:
        """A street by its street word, or a house number followed only by words (StreetRules::strong)."""
        if self._has_street_word(line, language):
            return line
        # A line that is only a phone number is not also a street, even though it starts with digits.
        if self._is_only_a_phone(line, language):
            return None
        trimmed = line.strip()
        return trimmed if self._NUMBER_FIRST.fullmatch(trimmed) else None

    def _after_name_street(self, line: str, language: str) -> Optional[str]:
        """An address that only counts once the name is read (StreetRules::afterName): in English a
        dwelling line ("Apt 4 Building 7"); in a number-last language only words, then the house number."""
        trimmed = line.strip()
        if language == 'en':
            return trimmed if self.DWELLING.fullmatch(trimmed) else None
        if language not in self.NUMBER_LAST_LANGUAGES:
            return None
        return trimmed if self._NUMBER_LAST.fullmatch(trimmed) else None

    def _street_word_offset(self, text: str, language: str) -> Optional[int]:
        """Where the street starts before a number-last house number ("GARCÍA Juan Calle Mayor" gives the
        offset of "Calle"): the first street word after the first two words, which belong to the name."""
        for match in self._street_pattern(language).finditer(text):
            offset = match.start()
            offset -= len(re.search(r"(?:[^\W\d_]|['’.\-])*$", text[:offset]).group())
            if len(text[:offset].split()) >= 2:
                return offset
        return None

    # ---- Businesses: same as the PHP ENTITY_TYPE_MARKERS and businessTypeOfName() ----

    ENTITY_TYPE_MARKERS = {
        'es': ['spa', 'srl', 'sa', 'ltda', 'inc', 'comercial', 'empresa', 'negocio', 'tienda', 'restaurante', 'hotel',
               'banco', 'farmacia', 'hospital', 'hnos', 'hermanos', 'hijos', 'sucesores', 'taller', 'ferretería',
               'clínica', 'panadería', 'carnicería', 'librería', 'zapatería'],
        'en': ['corp', 'inc', 'ltd', 'llc', 'company', 'store', 'shop', 'restaurant', 'hotel', 'bank', 'pharmacy',
               'hospital', 'sons', 'bros', 'associates', 'enterprises', 'industries', 'services', 'plumbing', 'garage',
               'bakery', 'hardware', 'clinic', 'pub', 'motel'],
        'fr': ['sarl', 'sas', 'eirl', 'eurl', 'magasin', 'restaurant', 'hôtel', 'banque', 'pharmacie', 'hôpital',
               'frères', 'boulangerie', 'boucherie', 'garage', 'clinique', 'quincaillerie', 'cabinet'],
        'pt': ['ltda', 'lda', 'eireli', 'empresa', 'comercial', 'loja', 'padaria', 'restaurante', 'hotel', 'banco',
               'farmácia', 'hospital', 'irmãos', 'filhos', 'oficina', 'clínica', 'mercearia', 'ferragens', 'sapataria'],
        'de': ['gmbh', 'ag', 'kg', 'ohg', 'apotheke', 'bäckerei', 'gasthaus', 'restaurant', 'hotel', 'bank',
               'krankenhaus', 'gebrüder', 'söhne', 'werkstatt', 'metzgerei', 'tischlerei', 'klinik'],
        'it': ['srl', 'spa', 'snc', 'sas', 'negozio', 'panificio', 'trattoria', 'ristorante', 'albergo', 'hotel',
               'banca', 'farmacia', 'ospedale', 'fratelli', 'figli', 'officina', 'macelleria', 'ferramenta',
               'pasticceria', 'pizzeria', 'clinica'],
    }

    def _extract_business_type(self, line: str, language: str) -> Optional[str]:
        markers = self.ENTITY_TYPE_MARKERS.get(language)
        if not markers:
            return None
        match = re.search(r'\b(' + '|'.join(re.escape(m) for m in markers) + r')\b', line, re.IGNORECASE)
        return match.group(1).lower() if match else None

    def _business_type_of_name(self, name: str, language: str) -> Optional[str]:
        """The business word in a name, unless the name is "<that word>, <given name>": "BANK, Henry" is a
        person whose surname happens to be a business word."""
        business_type = self._extract_business_type(name, language)
        if business_type is None:
            return None
        parts = [p.strip() for p in name.split(',')]
        is_surname_and_given_name = (
            len(parts) == 2
            and parts[0].lower() == business_type
            and re.fullmatch(r"[^\W\d_](?:[^\W\d_]|['. -])*", parts[1]) is not None
            and self._extract_business_type(parts[1], language) is None
        )
        return None if is_surname_and_given_name else business_type

    # ---- One record per line: same as the PHP SingleLineEntrySplitter ----

    _FIELD_DELIMITER = re.compile(r'\s*,\s*|(?:\.\s?){2,}|\t+|[ ]{2,}|[-–—]{2,}')
    _LEADERS = ' \t\n\r\0\x0b.,-–—'

    def _split_single_line(self, line: str, language: str) -> Optional[Tuple[str, str, Optional[str]]]:
        """(name, street, phone) when the line is a whole record, else None."""
        # A phone the scan misread ("555-l23-4567") would otherwise pass for a street starting with a digit.
        line = self.repair_ocr_digits(line)
        fields = [f.strip() for f in self._FIELD_DELIMITER.split(line) if f != '']

        if len(fields) >= 2:
            result = self._split_by_fields(fields, language)
            if result is not None:
                return result

        result = self._split_by_digit_boundary(line, language)
        if result is None:
            return None
        name, street, phone = result
        words = name.split()
        surname_count = PersonName.LANGUAGE_SURNAME_COUNT.get(language, 1)
        # "SMITH John" -> "SMITH, John": directories are surname-first even without a comma.
        if ',' not in name and len(words) > surname_count:
            name = ' '.join(words[:surname_count]) + ', ' + ' '.join(words[surname_count:])
        return name, street, phone

    def _split_by_fields(self, fields: List[str], language: str) -> Optional[Tuple[str, str, Optional[str]]]:
        phone = next((f for f in fields if self._is_only_a_phone(f, language)), None)

        street_index = next((i for i, f in enumerate(fields) if f != phone and self._has_street_word(f, language)), None)
        # No field reads as a street by its marker word: the first one that at least starts with a digit.
        if street_index is None:
            street_index = next((i for i, f in enumerate(fields) if f != phone and re.match(r'\d', f)), None)

        # A street field is only meaningful with a name field before it in the same record.
        if not street_index:
            return None

        name = ', '.join(f for f in fields[:street_index] if f != phone)
        street = fields[street_index]
        # A name with no letters is a house number cut off by its comma ("12, rue de la Paix"), not a name.
        if not re.search(r'[^\W\d_]', name) or street == '':
            return None
        return name, street, phone

    def _split_by_digit_boundary(self, line: str, language: str) -> Optional[Tuple[str, str, Optional[str]]]:
        rest = line
        phone = None
        match = self.phone_regex(language).search(line)
        if match:
            phone = match.group()
            # A line that opens with the phone ("555-123-4567 ext. 12") is the end of a record, not a whole one.
            if not re.search(r'[^\W\d_]', line[:match.start()]):
                return None
            rest = line[:match.start()] + line[match.end():]

        tail = re.search(r'\d.*', rest, re.DOTALL)
        if not tail:
            return None

        name = rest[:tail.start()].strip(self._LEADERS)
        street = tail.group().strip(self._LEADERS)

        if language in self.NUMBER_LAST_LANGUAGES and name:
            cut = self._street_word_offset(name, language)
            if cut is not None and 0 < cut < len(name):
                street = name[cut:] + ' ' + street
                name = name[:cut].strip(self._LEADERS)

        if not name or not street:
            return None
        # With no phone and no street word, a bare "word + number" is too ambiguous to split out.
        if phone is None and not self._has_street_word(street, language):
            return None
        return name, street, phone

    # ---- Blocks of lines: same as the PHP BlockReader and LineFacts ----

    def _process_buffer(self, lines: List[str], start_line: int, language: str):
        """Reads a block into records, looking at each line once.

        - A word the scan broke with a hyphen ("WASHING- / TON, George") is put back together.
        - Once a record has its name and street, a line that gives a name (a line of text, or a name beside
          a phone) starts the next record if a street follows it right away.
        - A street without a street word gives way to a later one with it.
        - Any other line of text after the name is a note, read for a business word.
        """
        joined, origin = [], []
        for i, line in enumerate(lines):
            if joined and re.search(r'[^\W\d_]-$', joined[-1]) and re.match(r'[^\W\d_]', line):
                joined[-1] = joined[-1][:-1] + line
                continue
            joined.append(line)
            origin.append(i)

        facts = []
        for line in joined:
            phone = self.phone_in(line, language)
            facts.append({
                'line': line,
                'phone': phone,
                'strong': self._extract_street(line, language),
                'after_name': self._after_name_street(line, language),
                'beside': None if phone is None else self.name_beside_phone(self.repair_ocr_digits(line), phone),
            })

        def street_of(fact, after_name):
            return fact['strong'] or (fact['after_name'] if after_name else None)

        def name_of(fact, after_name):
            if street_of(fact, after_name) is not None:
                return None
            return fact['line'] if fact['phone'] is None else fact['beside']

        def empty(offset):
            return {'offset': offset, 'name': None, 'phone': None, 'street': None, 'notes': []}

        records = []
        record = empty(0)
        strong_street = False
        for i, fact in enumerate(facts):
            has_name = record['name'] is not None

            if has_name and record['street'] is not None and name_of(fact, True) is not None \
                    and i + 1 < len(facts) and street_of(facts[i + 1], True) is not None:
                records.append(record)
                record = empty(origin[i])
                strong_street = False
                has_name = False

            if record['phone'] is None and fact['phone'] is not None:
                record['phone'] = fact['phone']

            street = street_of(fact, has_name)
            if street is not None and (record['street'] is None or (not strong_street and fact['strong'] is not None)):
                record['street'] = street
                strong_street = fact['strong'] is not None

            name = name_of(fact, has_name)
            if not has_name and name is not None:
                record['name'] = name
            elif has_name and fact['phone'] is None and street is None:
                record['notes'].append(fact['line'])
        records.append(record)

        for record in records:
            # Only the name and the notes can say it is a business, never the street.
            business_type = self._business_type_of_name(record['name'], language) if record['name'] else None
            for note in record['notes']:
                business_type = business_type or self._extract_business_type(note, language)
            self._finalize(record['name'], record['street'], record['phone'], business_type,
                           start_line + record['offset'], language)

    def _finalize(self, name: Optional[str], street: Optional[str], phone: Optional[str],
                  business_type: Optional[str], start_line: int, language: str):
        """Stores the record as a person or as a business, or as an error when it has no name or street."""
        data = {'name': name, 'phone': phone, 'street': street, 'country': self.country_code}
        if not name or not street:
            self.errors.append({'line': start_line, 'reason': 'Missing name or street', 'data': data})
            return

        try:
            entry = {
                'location': GeoLocation(self.country_code, street).to_dict(),
                'phone': phone,
                'record_date': datetime.now().isoformat(),
                'language': language,
            }
            if business_type is not None:
                entry.update({'type': 'juridical', 'business_name': name, 'business_type': business_type})
            else:
                entry.update({'type': 'natural', 'person_name': PersonName(name, language).to_dict()})
            self.entries.append(entry)
        except Exception as e:
            self.errors.append({'line': start_line, 'reason': str(e), 'data': data})


class PhoneDirectoryCatalog:
    """Historical phone directory catalog."""

    def __init__(self):
        self.directories = {
            'us_1878_ny': {
                'title': 'New York Telephone Directory',
                'year': 1878,
                'country': 'US',
                'city': 'New York',
                'estimated_entries': 3000,
            },
            'us_1950_national': {
                'title': 'AT&T Telephone Directory - National',
                'year': 1950,
                'country': 'US',
                'city': 'National',
                'estimated_entries': 2000000,
            },
            'gb_1880_london': {
                'title': 'London Telephone Directory',
                'year': 1880,
                'country': 'GB',
                'city': 'London',
                'estimated_entries': 5000,
            },
            'fr_1960_national': {
                'title': 'Annuaire National - France',
                'year': 1960,
                'country': 'FR',
                'city': 'National',
                'estimated_entries': 3000000,
            },
            'de_1970_national': {
                'title': 'Deutsche Telefonverzeichnis',
                'year': 1970,
                'country': 'DE',
                'city': 'National',
                'estimated_entries': 5000000,
            },
            'es_1975_national': {
                'title': 'Guía Telefónica Nacional - España',
                'year': 1975,
                'country': 'ES',
                'city': 'National',
                'estimated_entries': 4000000,
            },
        }

    def get_all(self) -> Dict:
        return self.directories

    def get_by_country(self, country: str) -> Dict:
        """Get directories by country code."""
        country = country.upper()
        return {
            k: v for k, v in self.directories.items()
            if v['country'] == country
        }

    def get_statistics(self) -> Dict:
        """Get catalog statistics."""
        all_dirs = list(self.directories.values())
        total_entries = sum(d.get('estimated_entries', 0) for d in all_dirs)
        years = sorted(set(d['year'] for d in all_dirs))
        countries = set(d['country'] for d in all_dirs)

        return {
            'total_directories': len(all_dirs),
            'total_countries': len(countries),
            'total_years': len(years),
            'total_entries': total_entries,
            'earliest_year': min(years),
            'latest_year': max(years),
            'countries': sorted(countries),
        }


class PhoneDirectoryDatabase:
    """SQLite database for phone directory entries."""

    def __init__(self, db_path: str = 'genealogy.db'):
        self.db_path = db_path
        self.conn = None
        self.cursor = None

    def connect(self):
        """Connect to database."""
        self.conn = sqlite3.connect(self.db_path)
        self.cursor = self.conn.cursor()

    def create_tables(self):
        """Create required tables."""
        self.cursor.execute('''
            CREATE TABLE IF NOT EXISTS phone_directory (
                id INTEGER PRIMARY KEY,
                first_names TEXT,
                last_names TEXT,
                country_code VARCHAR(2),
                zone TEXT,
                city TEXT,
                street TEXT NOT NULL,
                phone_number TEXT,
                record_date DATETIME,
                source_directory_id TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ''')

        self.cursor.execute('''
            CREATE INDEX IF NOT EXISTS idx_last_names
            ON phone_directory(last_names)
        ''')

        self.cursor.execute('''
            CREATE INDEX IF NOT EXISTS idx_country
            ON phone_directory(country_code)
        ''')

        self.conn.commit()

    def insert_entry(self, entry: Dict) -> int:
        """Insert a single entry."""
        person = entry.get('person_name', {})
        location = entry.get('location', {})

        self.cursor.execute('''
            INSERT INTO phone_directory
            (first_names, last_names, country_code, zone, city, street,
             phone_number, record_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ''', (
            ' '.join(person.get('first_names', [])),
            ' '.join(person.get('last_names', [])),
            location.get('country_code', 'US'),
            location.get('zone'),
            location.get('city'),
            location.get('street'),
            entry.get('phone'),
            entry.get('record_date')
        ))

        self.conn.commit()
        return self.cursor.lastrowid

    def find_by_last_name(self, last_name: str) -> List[Dict]:
        """Find entries by last name."""
        self.cursor.execute('''
            SELECT * FROM phone_directory
            WHERE last_names LIKE ?
            ORDER BY last_names, first_names
        ''', (f'%{last_name}%',))

        return [self._row_to_dict(row) for row in self.cursor.fetchall()]

    def find_by_country(self, country_code: str) -> List[Dict]:
        """Find entries by country."""
        self.cursor.execute('''
            SELECT * FROM phone_directory
            WHERE country_code = ?
            ORDER BY last_names, first_names
        ''', (country_code.upper(),))

        return [self._row_to_dict(row) for row in self.cursor.fetchall()]

    def count(self) -> int:
        """Get total count of entries."""
        self.cursor.execute('SELECT COUNT(*) FROM phone_directory')
        return self.cursor.fetchone()[0]

    def _row_to_dict(self, row: Tuple) -> Dict:
        """Convert database row to dictionary."""
        cols = ['id', 'first_names', 'last_names', 'country_code',
                'zone', 'city', 'street', 'phone_number', 'record_date',
                'source_directory_id', 'created_at']
        return dict(zip(cols, row))

    def close(self):
        """Close database connection."""
        if self.conn:
            self.conn.close()


class PhoneDirectoryManager:
    """High-level manager for phone directory operations."""

    def __init__(self, db_path: str = 'genealogy.db'):
        self.parser = MultiLanguagePhoneDirectoryParser()
        self.database = PhoneDirectoryDatabase(db_path)
        self.database.connect()
        self.database.create_tables()

    def process_file(self, filepath: str, language: Optional[str] = None) -> Dict:
        """Process a phone directory file."""
        entries = self.parser.parse_file(filepath, language)

        inserted = 0
        for entry in entries:
            if entry['type'] == 'natural':
                self.database.insert_entry(entry)
                inserted += 1

        return {
            'file': filepath,
            'language_detected': self.parser.detected_language,
            'total_parsed': len(entries),
            'inserted': inserted,
            'errors': len(self.parser.errors),
            'total_in_database': self.database.count()
        }

    def find_by_last_name(self, last_name: str) -> List[Dict]:
        """Find people by last name."""
        return self.database.find_by_last_name(last_name)

    def find_by_country(self, country_code: str) -> List[Dict]:
        """Find people by country."""
        return self.database.find_by_country(country_code)

    def get_statistics(self) -> Dict:
        """Get database statistics."""
        return {
            'total_records': self.database.count(),
            'detected_language': self.parser.detected_language,
            'parsing_errors': len(self.parser.errors)
        }

    def close(self):
        """Close database connection."""
        self.database.close()


# Example usage
if __name__ == '__main__':
    import os
    import tempfile

    workdir = tempfile.mkdtemp()
    manager = PhoneDirectoryManager(os.path.join(workdir, 'genealogy.db'))

    # Create sample directory
    sample_content = """
    García, Juan José
    Calle Principal 123
    555-1234567

    Smith, John Robert
    123 Main Street
    555-9876543
    """

    sample_path = os.path.join(workdir, 'sample_dir.txt')
    with open(sample_path, 'w', encoding='utf-8') as f:
        f.write(sample_content)

    result = manager.process_file(sample_path)
    print(f"Processed: {result['total_parsed']} entries")
    print(f"Inserted: {result['inserted']} entries")
    print(f"Language: {result['language_detected']}")

    # Query
    garcia_results = manager.find_by_last_name('García')
    print(f"\nFound {len(garcia_results)} García entries:")
    for entry in garcia_results:
        print(f"  {entry['first_names']} {entry['last_names']}")

    manager.close()
