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
        'es': ['calle', 'avenida', 'av', 'plaza', 'pasaje', 'camino', 'ruta', 'carrera'],
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

    PHONE_PATTERN = r'\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b|\b\d{10}\b'

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

    def detect_language(self, content: str) -> str:
        """Auto-detect language from whole-word street vocabulary; defaults to English."""
        scores = {'en': 0}
        for lang, words in self.DETECTION_MARKERS.items():
            pattern = self._words_pattern(words, self.STREET_SUFFIXES.get(lang, []))
            scores[lang] = len(pattern.findall(content)) if pattern else 0

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
            else:
                if not buffer:
                    buffer_start = line_number
                buffer.append(line)

        if buffer:
            self._process_buffer(buffer, buffer_start, language)

        return self.entries

    def _extract_street(self, line: str, language: str) -> Optional[str]:
        """Extract street address from line, matching street words only as whole words."""
        pattern = self._words_pattern(
            self.STREET_MARKERS.get(language, self.STREET_MARKERS['en']),
            self.STREET_SUFFIXES.get(language, [])
        )
        if pattern.search(line):
            return line

        # A line that is only a phone number is not also a street, even though it starts with digits.
        if re.fullmatch(r'[\d\s().+-]+', line):
            return None

        match = re.search(r'\d+\s+[\w\s]+', line)
        return match.group() if match else None

    def _process_buffer(self, lines: List[str], start_line: int, language: str):
        """Process a buffer of lines into an entry."""
        data = {
            'name': None,
            'phone': None,
            'street': None,
            'country': self.country_code,
        }

        for line in lines:
            if not data['phone']:
                match = re.search(self.PHONE_PATTERN, line)
                if match:
                    data['phone'] = match.group()

            if not data['street']:
                street = self._extract_street(line, language)
                if street:
                    data['street'] = street

            if not data['name'] and not re.search(self.PHONE_PATTERN, line) \
                    and not self._extract_street(line, language):
                data['name'] = line

        if not data['name'] or not data['street']:
            self.errors.append({
                'line': start_line,
                'reason': 'Missing name or street',
                'data': data
            })
            return

        try:
            entry = {
                'type': 'natural',
                'person_name': PersonName(data['name'], language).to_dict(),
                'location': GeoLocation(data['country'], data['street']).to_dict(),
                'phone': data['phone'],
                'record_date': datetime.now().isoformat(),
                'language': language
            }
            self.entries.append(entry)
        except Exception as e:
            self.errors.append({
                'line': start_line,
                'reason': str(e),
                'data': data
            })


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
