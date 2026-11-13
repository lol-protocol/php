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


# Letter and combining-mark classes: Python's re has no \p{L} or \p{M}.
_L = r"[^\W\d_]"
_M = "[̀-ͯ]"
_WORD = rf"{_L}(?:{_L}|{_M}|['’.\-])*"
# Not preceded / not followed by a letter or a digit.
_NOT_ALNUM_BEFORE = r"(?<![^\W_])"
_NOT_ALNUM_AFTER = r"(?![^\W_])"


class PhonePattern:
    """Phone formats, as in the PHP PhonePattern: digit groups, the named exchange of early directories
    ("BUtterfield 8-4521") and, per language, the formats of that language's countries."""

    REGEX = r'\b[A-Za-z]{3,}[ .-]\d[ -]\d{4}\b|\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b|\b\d{10}\b'

    # The looser formats are kept per language because they would read a house number as part of a phone
    # in another language's directory.
    LANGUAGE_FORMATS = {
        'fr': [r'(?:\+33[ .]?[1-9]|0[1-9])(?:[ .]?\d{2}){4}'],
        'de': [r'(?:\+49[ ]?[1-9]\d{1,3}|\(0\d{2,4}\)|0\d{2,4})[ /-]?\d{3,8}(?:-\d{1,4})?'],
        'it': [r'(?:\+39[ ]?)?0\d{1,3}[ /-]?\d{4,8}'],
        'pt': [r'(?:\+351[ ]?)?(?:[2-9]\d[ ]\d{3}[ ]\d{4}|[2-9]\d{2}[ ]\d{3}[ ]\d{3})'],
        'es': [r'(?:\+34[ ]?)?[6-9]\d[ ]\d{3}(?:[ ]\d{2}){2}',
               r'(?:\(\d{1,3}\)[ ]?|\d{1,3}[ -])\d{3}[ -]\d{3,4}'],
    }
    # Not preceded by a digit or a "+", not followed by a separator and a digit.
    _BEFORE = r'(?<![\d+])'
    _AFTER = r'(?![ ./-]?\d)'
    _cache: Dict[str, 're.Pattern'] = {}

    @classmethod
    def regex_for(cls, language: Optional[str] = None) -> 're.Pattern':
        """The base formats plus those of `language`'s countries (English and unknown languages: the base)."""
        key = language if language in cls.LANGUAGE_FORMATS else ''
        if key not in cls._cache:
            parts = [cls.REGEX] + [cls._BEFORE + '(?:' + f + ')' + cls._AFTER for f in cls.LANGUAGE_FORMATS.get(key, [])]
            cls._cache[key] = re.compile('|'.join(parts))
        return cls._cache[key]

    @classmethod
    def find(cls, line: str, regex: 're.Pattern') -> Optional[str]:
        """The phone in `line`, also when the scan misread a digit as a letter ("555-l23-4567"); None if none."""
        match = regex.search(line)
        if match:
            return match.group()
        repaired = cls.repair_ocr_digits(line)
        match = regex.search(repaired) if repaired != line else None
        return match.group() if match else None

    _OCR_CHEAP_TEST = re.compile(r'\d[ .\-]?[OolI]|[OolI][ .\-]?\d')
    _OCR_RUN = re.compile(r'(?<![^\W_])[\dOolI](?:[ .\-]?[\dOolI])+(?![^\W_])')

    @classmethod
    def repair_ocr_digits(cls, line: str) -> str:
        """"l" or "I" for 1 and "O" or "o" for 0 inside what looks like a phone number ("555-l23-4567" gives
        "555-123-4567"), only in a run with at least 6 real digits and at most 2 such letters, so words ("Oil")
        and exchange names ("BUtterfield 8-4521") are left alone."""
        if not cls._OCR_CHEAP_TEST.search(line):
            return line

        def fix(m):
            digits = len(re.findall(r'\d', m.group()))
            letters = len(re.findall(r'[OolI]', m.group()))
            return m.group().translate(str.maketrans('OolI', '0011')) if 0 < letters <= 2 and digits >= 6 else m.group()
        return cls._OCR_RUN.sub(fix, line)

    @staticmethod
    def name_beside_phone(line: str, phone: str) -> Optional[str]:
        """What a line says besides its phone, when that could be a name: "SMITH, John ........ 555-123-4567"
        gives "SMITH, John". None when nothing is left or what is left has a digit ("555-123-4567 ext. 12")."""
        offset = line.find(phone)
        if offset < 0:
            return None
        rest = (line[:offset] + ' ' + line[offset + len(phone):]).strip(' \t.,:;-–—')
        return rest if re.search(_L, rest) and not re.search(r'\d', rest) else None

    @classmethod
    def is_only_a_phone(cls, text: str, language: Optional[str] = None) -> bool:
        """True when `text`, trimmed, is nothing but a single phone match (so it is not also a street)."""
        trimmed = text.strip()
        match = cls.regex_for(language).search(trimmed) if trimmed else None
        return bool(match) and match.group() == trimmed


class StreetFallback:
    """A street with no street-type word ("9 Wexford Close", "8 Clos Saint-Éloi"): the whole line is a house
    number followed only by words. Same three shapes as the PHP StreetFallback."""

    _NUMBER_FIRST = re.compile(rf"\d{{1,5}}[a-z]?\s+{_WORD}(?:\s+{_WORD})*", re.IGNORECASE)
    _NUMBER_LAST = re.compile(rf"{_WORD}(?:\s+{_WORD})*\s+\d{{1,5}}[a-z]?", re.IGNORECASE)
    _DWELLING = re.compile(r"(?:apt|apartment|unit|flat|suite|ste|bldg|building)\.?\s*#?\d{1,5}[a-z]?"
                           rf"(?:\s+(?:{_L}|\d|#)(?:{_L}|{_M}|\d|['’.\-#])*)*", re.IGNORECASE)

    @classmethod
    def number_first(cls, line: str) -> Optional[str]:
        trimmed = line.strip()
        return trimmed if cls._NUMBER_FIRST.fullmatch(trimmed) else None

    @classmethod
    def number_last(cls, line: str) -> Optional[str]:
        """"Los Aromos 123", "Travessa do Carmo 5": only words, then the house number. Also the shape of
        "Restaurante Siglo 21", so callers only ask once the record's name has been read."""
        trimmed = line.strip()
        return trimmed if cls._NUMBER_LAST.fullmatch(trimmed) else None

    @classmethod
    def dwelling(cls, line: str) -> Optional[str]:
        """"Apt 4 Building 7", "Unit 3", "Flat 2B Rose Court": an English address that opens with the dwelling."""
        trimmed = line.strip()
        return trimmed if cls._DWELLING.fullmatch(trimmed) else None


class StreetRules:
    """What counts as a street, per language (same rules as the PHP StreetRules): strong (a street word, or a
    house number first and only words after it) or weak (the house number last, or a dwelling line, which
    only count once the record's name is read)."""

    SUPPORTED_LANGUAGES = ['es', 'en', 'fr', 'pt', 'de', 'it']
    # Languages whose addresses put the house number after the street name ("Calle Mayor 12", "Via Roma 10").
    NUMBER_LAST_LANGUAGES = ('es', 'pt', 'it', 'de')

    MARKERS = {
        'es': ['calle', 'c/', 'avenida', 'av', 'plaza', 'pasaje', 'camino', 'ruta', 'carrera'],
        'en': ['street', 'st', 'avenue', 'ave', 'road', 'rd', 'drive', 'dr', 'lane', 'ln',
               'boulevard', 'blvd', 'circle', 'cir'],
        'fr': ['rue', 'avenue', 'allée', 'place', 'boulevard', 'bd', 'cours', 'square'],
        'pt': ['rua', 'avenida', 'av', 'praça', 'alameda', 'estrada', 'largo'],
        'de': ['ring', 'hof'],
        'it': ['via', 'viale', 'corso', 'piazza', 'largo', 'strada'],
    }
    # German compounds glue the street type onto the name ("Hauptstraße"), so these match as word endings.
    SUFFIXES = {'de': ['straße', 'strasse', 'allee', 'weg', 'platz']}
    # Street words that are also the usual abbreviation opening a name ("Dr." doctor, "St." saint): they only
    # make a street when the line has a digit or they are not its first word.
    NAME_ABBREVIATIONS = {'en': ['dr', 'st']}

    _patterns: Dict[str, 're.Pattern'] = {}
    _abbreviations: Dict[str, Optional['re.Pattern']] = {}

    @classmethod
    def pattern(cls, language: str) -> 're.Pattern':
        """The street words of `language` as a regex; the NAME_ABBREVIATIONS are not in it."""
        if language not in cls._patterns:
            if language not in cls.MARKERS:
                raise ValueError(f"Unsupported language for street pattern: {language}. "
                                 f"Supported languages: {', '.join(cls.SUPPORTED_LANGUAGES)}")
            markers = [m for m in cls.MARKERS[language] if m not in cls.NAME_ABBREVIATIONS.get(language, [])]
            # Not \b: "c/" ends in a slash, and \b only sees a word edge after a letter or digit.
            body = _NOT_ALNUM_BEFORE + '(?:' + '|'.join(re.escape(m) for m in markers) + ')' + _NOT_ALNUM_AFTER
            suffixes = [re.escape(s) for s in cls.SUFFIXES.get(language, [])]
            if suffixes:
                body += '|(?:' + '|'.join(suffixes) + r')\b'
            cls._patterns[language] = re.compile(body, re.IGNORECASE)
        return cls._patterns[language]

    @classmethod
    def _abbreviation_pattern(cls, language: str) -> Optional['re.Pattern']:
        if language not in cls._abbreviations:
            words = cls.NAME_ABBREVIATIONS.get(language, [])
            cls._abbreviations[language] = re.compile(
                _NOT_ALNUM_BEFORE + '(?:' + '|'.join(re.escape(w) for w in words) + ')' + _NOT_ALNUM_AFTER,
                re.IGNORECASE) if words else None
        return cls._abbreviations[language]

    @classmethod
    def has_street_word(cls, line: str, language: str) -> bool:
        """Whether the line has a street word (a strong street)."""
        if cls.pattern(language).search(line):
            return True
        abbreviation = cls._abbreviation_pattern(language)
        matches = list(abbreviation.finditer(line)) if abbreviation else []
        if not matches:
            return False
        if re.search(r'\d', line):
            return True
        return any(line[:m.start()].strip() != '' for m in matches)

    @classmethod
    def strong(cls, line: str, language: str) -> Optional[str]:
        """The line, when it is a street with a street word or a house number first; None otherwise."""
        if cls.has_street_word(line, language):
            return line
        if PhonePattern.is_only_a_phone(line, language):
            return None
        return StreetFallback.number_first(line)

    @classmethod
    def after_name(cls, line: str, language: str) -> Optional[str]:
        """An address that only counts once the record's name is read: a street with no street word in a
        language that writes the house number last ("Los Aromos 123"); a dwelling line in English."""
        if language == 'en':
            return StreetFallback.dwelling(line)
        return StreetFallback.number_last(line) if language in cls.NUMBER_LAST_LANGUAGES else None

    @classmethod
    def street_word_offset(cls, text: str, language: str) -> Optional[int]:
        """Where the street starts in the text before a number-last house number ("GARCÍA Juan Calle Mayor"
        gives the offset of "Calle"): the first street word after the first two words, which belong to the
        name. A street word that is a suffix ("Hauptstraße") starts the street at the start of its word."""
        for match in cls.pattern(language).finditer(text):
            before = text[:match.start()]
            word_start = re.search(rf"(?:{_L}|{_M}|['’.\-])*$", before)
            offset = match.start() - len(word_start.group())
            if len(re.findall(r'\S+', text[:offset])) >= 2:
                return offset
        return None


@dataclass(frozen=True)
class LineFacts:
    """What one line of a block says, worked out once: its phone, its street (with or without the record's
    name already read) and, for a phone line, the name beside the phone."""

    line: str
    phone: Optional[str]
    strong_street: Optional[str]
    after_name_street: Optional[str]
    name_beside_phone: Optional[str]

    @classmethod
    def of(cls, line: str, language: str, phone_regex: 're.Pattern') -> 'LineFacts':
        phone = PhonePattern.find(line, phone_regex)
        return cls(
            line, phone,
            StreetRules.strong(line, language),
            StreetRules.after_name(line, language),
            None if phone is None else PhonePattern.name_beside_phone(PhonePattern.repair_ocr_digits(line), phone))

    def street(self, after_name: bool) -> Optional[str]:
        """The street this line gives, depending on whether the record's name has been read."""
        if self.strong_street is not None:
            return self.strong_street
        return self.after_name_street if after_name else None

    def name(self, after_name: bool) -> Optional[str]:
        """The name this line can give: a line of text, or what a phone line has beside its phone."""
        if self.street(after_name) is not None:
            return None
        return self.line if self.phone is None else self.name_beside_phone


class BlockReader:
    """Reads a block of lines (between two blank lines) into records, looking at each line once (LineFacts).
    Same rules as the PHP BlockReader: words the scan cut with a hyphen are put back together; a block can hold
    several records, a new one starting at a name when a street follows it right away; the street is the first
    one unless it was weak and a strong one comes later; any other text after the name is a note."""

    @classmethod
    def read(cls, lines: List[str], classify) -> List[Dict]:
        lines, origin = cls._join_hyphenated_words(lines)
        facts = [classify(line) for line in lines]

        records: List[Dict] = []
        record = cls._empty_record(0)
        strong_street = False

        for i, line in enumerate(facts):
            has_name = record['name'] is not None

            if (has_name and record['street'] is not None and line.name(True) is not None
                    and i + 1 < len(facts) and facts[i + 1].street(True) is not None):
                records.append(record)
                record = cls._empty_record(origin[i])
                strong_street = False
                has_name = False

            if record['phone'] is None and line.phone is not None:
                record['phone'] = line.phone

            street = line.street(has_name)
            if street is not None and (record['street'] is None or (not strong_street and line.strong_street is not None)):
                record['street'] = street
                strong_street = line.strong_street is not None

            name = line.name(has_name)
            if not has_name and name is not None:
                record['name'] = name
            elif has_name and line.phone is None and street is None:
                record['notes'].append(line.line)

        records.append(record)
        return records

    @staticmethod
    def _empty_record(offset: int) -> Dict:
        return {'offset': offset, 'name': None, 'phone': None, 'street': None, 'notes': []}

    @staticmethod
    def _join_hyphenated_words(lines: List[str]):
        """"WASHING- / TON, George" becomes "WASHINGTON, George" (a name cut at its own hyphen loses it)."""
        joined: List[str] = []
        origin: List[int] = []
        for i, line in enumerate(lines):
            if joined and re.search(rf'{_L}-$', joined[-1]) and re.match(_L, line):
                joined[-1] = joined[-1][:-1] + line
                continue
            joined.append(line)
            origin.append(i)
        return joined, origin


class SingleLineEntrySplitter:
    """Splits a single "NAME ... STREET ... PHONE" line, the layout most historical directories use: by
    delimited fields (commas, dot leaders, tabs, runs of spaces or dashes) or, with none, at the first digit
    once the phone is out. Same strategies as the PHP SingleLineEntrySplitter."""

    LEADER_CHARS = " \t\n\r\0\x0b.,-–—"
    # At least 2 dots for a dot leader (so "Mrs. John" and "St. Louis" do not split) and at least 2 spaces or dashes.
    FIELD_DELIMITER = re.compile(r'\s*,\s*|(?:\.\s?){2,}|\t+|[ ]{2,}|[-–—]{2,}')

    @classmethod
    def split(cls, line: str, looks_like_street, surname_word_count: int = 1,
              language: Optional[str] = None, street_start=None) -> Optional[Dict]:
        # A phone the scan misread ("555-l23-4567") would otherwise pass for a street starting with a digit.
        line = PhonePattern.repair_ocr_digits(line)
        fields = [f.strip(' \n\r\t\x0b\0') for f in cls.FIELD_DELIMITER.split(line) if f]

        if len(fields) >= 2:
            result = cls._split_by_fields(fields, looks_like_street, language)
            if result is not None:
                return result

        result = cls._split_by_digit_boundary(line, looks_like_street, language, street_start)
        if result is not None:
            result['name'] = cls._assume_surname_first(result['name'], surname_word_count)
        return result

    @staticmethod
    def _assume_surname_first(name: str, surname_word_count: int) -> str:
        """"SMITH John" -> "SMITH, John"; leaves an already-comma'd or too-short name alone."""
        if ',' in name:
            return name
        words = name.split()
        if len(words) <= surname_word_count:
            return name
        return f"{' '.join(words[:surname_word_count])}, {' '.join(words[surname_word_count:])}"

    @classmethod
    def _split_by_fields(cls, fields: List[str], looks_like_street, language: Optional[str]) -> Optional[Dict]:
        phone = next((f for f in fields if PhonePattern.is_only_a_phone(f, language)), None)

        street_index = next((i for i, f in enumerate(fields) if f != phone and looks_like_street(f)), None)
        # No field alone reads as a street by its marker word: the first that at least starts with a digit.
        if street_index is None:
            street_index = next((i for i, f in enumerate(fields) if f != phone and re.match(r'\d', f)), None)

        # A street field is only meaningful with a name field before it in the same record.
        if street_index is None or street_index == 0:
            return None

        name = ', '.join(f for i, f in enumerate(fields) if i < street_index and f != phone)
        street = fields[street_index]

        # A name with no letters is a house number cut off by its comma ("12, rue de la Paix"), not a name.
        if not re.search(_L, name) or street == '':
            return None
        return {'name': name, 'street': street, 'phone': phone}

    @classmethod
    def _split_by_digit_boundary(cls, line: str, looks_like_street, language: Optional[str], street_start) -> Optional[Dict]:
        rest = line
        phone = None

        match = PhonePattern.regex_for(language).search(line)
        if match:
            phone = match.group()
            # A line that opens with the phone ("555-123-4567 ext. 12") is the end of a record, not a whole one.
            if not re.search(_L, line[:match.start()]):
                return None
            rest = line[:match.start()] + line[match.end():]

        tail = re.search(r'\d.*', rest, re.DOTALL)
        if not tail:
            return None

        name = rest[:tail.start()].strip(cls.LEADER_CHARS)
        street = tail.group().strip(cls.LEADER_CHARS)

        cut = street_start(name) if street_start is not None and name != '' else None
        if cut is not None and 0 < cut < len(name):
            street = name[cut:] + ' ' + street
            name = name[:cut].strip(cls.LEADER_CHARS)

        if name == '' or street == '':
            return None

        # With no phone and no recognized street word, a bare "word + number" is too ambiguous to split out.
        if phone is None and not looks_like_street(street):
            return None
        return {'name': name, 'street': street, 'phone': phone}


class MultiLanguagePhoneDirectoryParser:
    """Parse phone directories in multiple languages (same parsing as the PHP MultiLanguagePhoneDirectoryParser:
    one-line entries, blocks of lines, businesses and persons)."""

    STREET_MARKERS = StreetRules.MARKERS
    STREET_SUFFIXES = StreetRules.SUFFIXES

    # Only vocabulary distinctive enough to override English, the baseline language; words that are also
    # English ("plaza", "avenue", "via") would otherwise misdetect an English directory.
    DETECTION_MARKERS = {
        'es': ['calle', 'avenida', 'pasaje', 'camino', 'ruta', 'carrera'],
        'fr': ['rue', 'allée', 'cours'],
        'pt': ['rua', 'avenida', 'praça', 'alameda', 'estrada', 'largo'],
        'de': [],
        'it': ['viale', 'corso', 'piazza', 'largo', 'strada'],
    }

    # Words that name a kind of business, or a family firm ("Smith & Sons", "Hnos."), and that almost nobody
    # has for a surname. A word many people carry as a surname (Baker, Miller, Bank...) does not belong here.
    ENTITY_TYPE_MARKERS = {
        'es': ['spa', 'srl', 'sa', 'ltda', 'inc', 'comercial', 'empresa', 'negocio', 'tienda', 'restaurante', 'hotel', 'banco', 'farmacia', 'hospital', 'hnos', 'hermanos', 'hijos', 'sucesores', 'taller', 'ferretería', 'clínica', 'panadería', 'carnicería', 'librería', 'zapatería'],
        'en': ['corp', 'inc', 'ltd', 'llc', 'company', 'store', 'shop', 'restaurant', 'hotel', 'bank', 'pharmacy', 'hospital', 'sons', 'bros', 'associates', 'enterprises', 'industries', 'services', 'plumbing', 'garage', 'bakery', 'hardware', 'clinic', 'pub', 'motel'],
        'fr': ['sarl', 'sas', 'eirl', 'eurl', 'magasin', 'restaurant', 'hôtel', 'banque', 'pharmacie', 'hôpital', 'frères', 'boulangerie', 'boucherie', 'garage', 'clinique', 'quincaillerie', 'cabinet'],
        'pt': ['ltda', 'lda', 'eireli', 'empresa', 'comercial', 'loja', 'padaria', 'restaurante', 'hotel', 'banco', 'farmácia', 'hospital', 'irmãos', 'filhos', 'oficina', 'clínica', 'mercearia', 'ferragens', 'sapataria'],
        'de': ['gmbh', 'ag', 'kg', 'ohg', 'apotheke', 'bäckerei', 'gasthaus', 'restaurant', 'hotel', 'bank', 'krankenhaus', 'gebrüder', 'söhne', 'werkstatt', 'metzgerei', 'tischlerei', 'klinik'],
        'it': ['srl', 'spa', 'snc', 'sas', 'negozio', 'panificio', 'trattoria', 'ristorante', 'albergo', 'hotel', 'banca', 'farmacia', 'ospedale', 'fratelli', 'figli', 'officina', 'macelleria', 'ferramenta', 'pasticceria', 'pizzeria', 'clinica'],
    }

    SEPARATOR_PATTERN = re.compile(r'^(?:[-=_*]\s*){2,}$')

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

    # "Via Roma 10" opens the line and ends with the number, a shape English doesn't use ("12 Via Lane"),
    # so "via" counts as Italian only there.
    DETECTION_LINES = {
        'it': re.compile(r"^[ \t]*via\s+[^\W\d_](?:[^\W\d_]|['’.\-])*(?:\s+[^\W\d_](?:[^\W\d_]|['’.\-])*)*,?\s+\d{1,5}[a-z]?\b",
                         re.IGNORECASE | re.MULTILINE),
    }

    def detect_language(self, content: str) -> str:
        """Auto-detect language from whole-word street vocabulary; defaults to English."""
        scores = {'en': 0}
        for lang, words in self.DETECTION_MARKERS.items():
            pattern = self._words_pattern(words, StreetRules.SUFFIXES.get(lang, []))
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
        self.entries = []
        self.errors = []

        if language is None:
            language = self.detect_language(content)
        if language not in StreetRules.SUPPORTED_LANGUAGES:
            raise ValueError(f"Unsupported language: {language}. "
                             f"Supported languages: {', '.join(StreetRules.SUPPORTED_LANGUAGES)}")
        self.detected_language = language
        phone_regex = PhonePattern.regex_for(language)

        buffer: List[str] = []
        buffer_start = 0
        surname_count = PersonName.LANGUAGE_SURNAME_COUNT.get(language, 1)
        street_start = ((lambda before: StreetRules.street_word_offset(before, language))
                        if language in StreetRules.NUMBER_LAST_LANGUAGES else None)

        for line_number, line in enumerate(content.split('\n'), 1):
            trimmed = line.strip()

            if trimmed == '' or self.SEPARATOR_PATTERN.match(trimmed):
                if buffer:
                    self._process_buffer(buffer, buffer_start, language, phone_regex)
                    buffer = []
                continue

            # Most historical directories print one full record per line: try that shape first. Only a street
            # word counts as a street there (the number-first fallback would turn "Apt 4 Building 7" into a record).
            single = SingleLineEntrySplitter.split(
                trimmed, lambda street: StreetRules.has_street_word(street, language),
                surname_count, language, street_start)
            if single is not None:
                if buffer:
                    self._process_buffer(buffer, buffer_start, language, phone_regex)
                    buffer = []
                self._finalize_entry({'name': single['name'], 'phone': single['phone'], 'street': single['street'],
                                      'type': self._business_type_of_name(single['name'], language)},
                                     line_number, language)
                continue

            if not buffer:
                buffer_start = line_number
            buffer.append(trimmed)

        if buffer:
            self._process_buffer(buffer, buffer_start, language, phone_regex)

        return self.entries

    def _process_buffer(self, lines: List[str], start_line: int, language: str, phone_regex):
        records = BlockReader.read(lines, lambda line: LineFacts.of(line, language, phone_regex))
        for record in records:
            # Only the name and the notes can say it is a business: "12 Bank Street" says where someone lives.
            business = self._business_type_of_name(record['name'], language) if record['name'] is not None else None
            for note in record['notes']:
                if business is None:
                    business = self._extract_business_type(note, language)
            self._finalize_entry({'name': record['name'], 'phone': record['phone'], 'street': record['street'],
                                  'type': business}, start_line + record['offset'], language)

    def _finalize_entry(self, data: Dict, start_line: int, language: str):
        if not data['name'] or not data['street']:
            self.errors.append({'line': start_line, 'reason': 'Missing name or street', 'data': data})
            return

        try:
            location = GeoLocation(self.country_code, data['street']).to_dict()
            if self._is_juridical(data, language):
                entry = {
                    'type': 'juridical',
                    'business_name': data['name'],
                    'business_type': data['type'],
                    'location': location,
                    'phone': data['phone'],
                    'record_date': datetime.now().isoformat(),
                    'language': language,
                }
            else:
                entry = {
                    'type': 'natural',
                    'person_name': PersonName(data['name'], language).to_dict(),
                    'location': location,
                    'phone': data['phone'],
                    'record_date': datetime.now().isoformat(),
                    'language': language,
                }
            self.entries.append(entry)
        except Exception as e:
            self.errors.append({'line': start_line, 'reason': str(e), 'data': data})

    _entity_patterns: Dict[str, Optional['re.Pattern']] = {}

    def _extract_business_type(self, line: str, language: str) -> Optional[str]:
        markers = self.ENTITY_TYPE_MARKERS.get(language, [])
        if not markers:
            return None
        if language not in self._entity_patterns:
            self._entity_patterns[language] = re.compile(
                r'\b(' + '|'.join(re.escape(m) for m in markers) + r')\b', re.IGNORECASE)
        match = self._entity_patterns[language].search(line)
        return match.group(1).lower() if match else None

    def _business_type_of_name(self, name: str, language: str) -> Optional[str]:
        """The business word in a name, unless the name is a surname followed by a given name: "BANK, Henry" is a
        person whose surname happens to be a business word. Only the exact shape "<one word>, <given name>" is
        spared, with no business word in the given-name part: "BANK OF AMERICA, N.A." stays a business."""
        business = self._extract_business_type(name, language)
        if business is None:
            return None

        parts = [p.strip() for p in name.split(',')]
        is_surname_and_given_name = (
            len(parts) == 2
            and parts[0].lower() == business
            and re.fullmatch(rf"{_L}(?:{_L}|['. -])*", parts[1]) is not None
            and self._extract_business_type(parts[1], language) is None)
        return None if is_surname_and_given_name else business

    def _is_juridical(self, data: Dict, language: str) -> bool:
        if data.get('type'):
            return True
        return self._business_type_of_name(data.get('name') or '', language) is not None



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
