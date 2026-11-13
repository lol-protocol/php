/**
 * Phone Directory Parser - Java Implementation
 * Genealogical phone directory parsing with multi-language support
 */

import java.sql.*;
import java.time.LocalDateTime;
import java.util.*;
import java.util.function.Function;
import java.util.function.Predicate;
import java.util.regex.*;


/**
 * Parse and decompose personal names into components.
 */
class PersonName {
    private final String fullName;
    private final String language;
    private List<String> firstNames;
    private List<String> lastNames;

    // Same rules as the PHP PersonName.
    private static final Set<String> PARTICLES = Set.of(
        "de", "del", "della", "di", "da", "das", "do", "dos", "du",
        "van", "von", "der", "den", "le", "la", "los", "las"
    );

    // Single-letter connectors collide with middle initials ("John E Smith"), so they only count when the language uses them.
    private static final Map<String, Set<String>> LANGUAGE_CONNECTORS = Map.of(
        "es", Set.of("y"),
        "pt", Set.of("e"),
        "it", Set.of("e")
    );

    private static final Map<String, Integer> LANGUAGE_SURNAME_COUNT = Map.of("es", 2, "pt", 2);

    /** How many words of a name without a comma are the surname in {@code language} (1 unless it is two). */
    static int surnameCount(String language) {
        return LANGUAGE_SURNAME_COUNT.getOrDefault(language, 1);
    }

    public PersonName(String fullName) {
        this(fullName, null);
    }

    public PersonName(String fullName, String language) {
        if (fullName == null || fullName.trim().isEmpty()) {
            throw new IllegalArgumentException("Full name cannot be empty");
        }
        this.fullName = fullName;
        this.language = language;
        parseName();
    }

    private boolean isParticle(String word) {
        String lower = word.toLowerCase();
        // Immutable Map.of() maps reject null keys even in getOrDefault(), hence the explicit check.
        return PARTICLES.contains(lower)
            || (language != null && LANGUAGE_CONNECTORS.getOrDefault(language, Set.of()).contains(lower));
    }

    private String normalizeCase(String word, boolean isLeading) {
        if (!isLeading && isParticle(word)) {
            return word.toLowerCase();
        }
        String lower = word.toLowerCase();
        return lower.isEmpty() ? lower : Character.toUpperCase(lower.charAt(0)) + lower.substring(1);
    }

    private List<String> normalizeAll(String text) {
        List<String> words = new ArrayList<>();
        for (String w : text.trim().split("\\s+")) {
            if (!w.isEmpty()) {
                words.add(normalizeCase(w, words.isEmpty()));
            }
        }
        return words;
    }

    private void parseName() {
        String name = fullName.trim();

        if (name.contains(",")) {
            int comma = name.indexOf(',');
            lastNames = new ArrayList<>();
            for (String w : name.substring(0, comma).trim().split("\\s+")) {
                if (!w.isEmpty()) {
                    lastNames.add(normalizeCase(w, false));
                }
            }
            firstNames = normalizeAll(name.substring(comma + 1));
            return;
        }

        List<String> parts = normalizeAll(name);

        // Walk backwards taking one surname per iteration, pulling in any particles that precede it
        // ("de la Cruz", "van der Rohe"); the first token always stays a given name.
        int surnameCount = language == null ? 1 : LANGUAGE_SURNAME_COUNT.getOrDefault(language, 1);
        LinkedList<String> surnames = new LinkedList<>();
        int taken = 0;
        while (taken < surnameCount && parts.size() > 1) {
            LinkedList<String> group = new LinkedList<>();
            group.addFirst(parts.remove(parts.size() - 1));
            while (parts.size() > 1 && isParticle(parts.get(parts.size() - 1))) {
                group.addFirst(parts.remove(parts.size() - 1));
            }
            surnames.addAll(0, group);
            taken++;
        }

        firstNames = parts;
        lastNames = new ArrayList<>(surnames);
    }

    public List<String> getFirstNames() {
        return new ArrayList<>(firstNames);
    }

    public String getFirstName() {
        return firstNames.isEmpty() ? "" : firstNames.get(0);
    }

    public List<String> getMiddleNames() {
        return firstNames.size() > 1
            ? new ArrayList<>(firstNames.subList(1, firstNames.size()))
            : new ArrayList<>();
    }

    public List<String> getLastNames() {
        return new ArrayList<>(lastNames);
    }

    public String getPrimaryLastName() {
        return lastNames.isEmpty() ? "" : lastNames.get(0);
    }

    /** The name as the directory prints it ("SMITH, John"), like getRawName() in PHP. */
    public String getRawName() {
        return fullName;
    }

    public String getFullName() {
        List<String> all = new ArrayList<>(firstNames);
        all.addAll(lastNames);
        return String.join(" ", all);
    }

    public String getFormatted() {
        if (lastNames.isEmpty()) {
            return String.join(" ", firstNames);
        }
        String lastStr = String.join(" ", lastNames);
        String firstStr = String.join(" ", firstNames);
        return lastStr + ", " + firstStr;
    }

    public Map<String, Object> toMap() {
        Map<String, Object> map = new HashMap<>();
        map.put("fullName", getFullName());
        map.put("rawName", getRawName());
        map.put("firstNames", getFirstNames());
        map.put("lastNames", getLastNames());
        map.put("primaryLastName", getPrimaryLastName());
        map.put("formatted", getFormatted());
        return map;
    }
}


/**
 * Geographic location with country, zone, city, street.
 */
class GeoLocation {
    private final String countryCode;
    private final String street;
    private final String zone;
    private final String city;

    public GeoLocation(String countryCode, String street, String zone, String city) {
        if (countryCode == null || !countryCode.matches("[A-Za-z]{2}")) {
            throw new IllegalArgumentException("Country code must be 2 letters");
        }
        this.countryCode = countryCode.toUpperCase();
        this.street = street;
        this.zone = zone;
        this.city = city;
    }

    public GeoLocation(String countryCode, String street) {
        this(countryCode, street, null, null);
    }

    public String getCountryCode() { return countryCode; }
    public String getStreet() { return street; }
    public String getZone() { return zone; }
    public String getCity() { return city; }

    public String getFullAddress() {
        List<String> parts = new ArrayList<>();
        parts.add(street);
        if (city != null) parts.add(city);
        if (zone != null) parts.add(zone);
        parts.add(countryCode);
        return String.join(", ", parts);
    }

    public Map<String, Object> toMap() {
        Map<String, Object> map = new HashMap<>();
        map.put("countryCode", countryCode);
        map.put("zone", zone);
        map.put("city", city);
        map.put("street", street);
        map.put("fullAddress", getFullAddress());
        return map;
    }
}


/**
 * Phone formats, as in the PHP PhonePattern: digit groups, the named exchange of early directories
 * ("BUtterfield 8-4521") and, per language, the formats of that language's countries.
 */
final class PhonePattern {
    private PhonePattern() {
    }

    private static final String BASE =
        "\\b[A-Za-z]{3,}[ .-]\\d[ -]\\d{4}\\b|\\b\\d{3}[-.\\s]?\\d{3}[-.\\s]?\\d{4}\\b|\\b\\d{10}\\b";

    // The looser formats are kept per language because they would read a house number as part of a phone
    // in another language's directory.
    private static final Map<String, List<String>> LANGUAGE_FORMATS = Map.of(
        "fr", List.of("(?:\\+33[ .]?[1-9]|0[1-9])(?:[ .]?\\d{2}){4}"),
        "de", List.of("(?:\\+49[ ]?[1-9]\\d{1,3}|\\(0\\d{2,4}\\)|0\\d{2,4})[ /-]?\\d{3,8}(?:-\\d{1,4})?"),
        "it", List.of("(?:\\+39[ ]?)?0\\d{1,3}[ /-]?\\d{4,8}"),
        "pt", List.of("(?:\\+351[ ]?)?(?:[2-9]\\d[ ]\\d{3}[ ]\\d{4}|[2-9]\\d{2}[ ]\\d{3}[ ]\\d{3})"),
        "es", List.of("(?:\\+34[ ]?)?[6-9]\\d[ ]\\d{3}(?:[ ]\\d{2}){2}",
                      "(?:\\(\\d{1,3}\\)[ ]?|\\d{1,3}[ -])\\d{3}[ -]\\d{3,4}")
    );
    // Not preceded by a digit or a "+", not followed by a separator and a digit.
    private static final String BEFORE = "(?<![\\d+])";
    private static final String AFTER = "(?![ ./-]?\\d)";
    private static final Map<String, Pattern> CACHE = new HashMap<>();

    /** The base formats plus those of {@code language}'s countries (English and unknown languages: the base). */
    static synchronized Pattern regexFor(String language) {
        String key = language != null && LANGUAGE_FORMATS.containsKey(language) ? language : "";
        return CACHE.computeIfAbsent(key, k -> {
            StringBuilder regex = new StringBuilder(BASE);
            for (String format : LANGUAGE_FORMATS.getOrDefault(k, List.of())) {
                regex.append('|').append(BEFORE).append("(?:").append(format).append(')').append(AFTER);
            }
            return Pattern.compile(regex.toString());
        });
    }

    /** The phone in {@code line}, also when the scan misread a digit as a letter ("555-l23-4567"); null if none. */
    static String find(String line, Pattern regex) {
        Matcher match = regex.matcher(line);
        if (match.find()) {
            return match.group();
        }
        String repaired = repairOcrDigits(line);
        if (repaired.equals(line)) {
            return null;
        }
        match = regex.matcher(repaired);
        return match.find() ? match.group() : null;
    }

    private static final Pattern OCR_CHEAP_TEST = Pattern.compile("\\d[ .\\-]?[OolI]|[OolI][ .\\-]?\\d");
    private static final Pattern OCR_RUN =
        Pattern.compile("(?<![\\p{L}\\d])[\\dOolI](?:[ .\\-]?[\\dOolI])+(?![\\p{L}\\d])");

    /**
     * "l" or "I" for 1 and "O" or "o" for 0 inside what looks like a phone number ("555-l23-4567" gives
     * "555-123-4567"), only in a run with at least 6 real digits and at most 2 such letters, so words ("Oil")
     * and exchange names ("BUtterfield 8-4521") are left alone.
     */
    static String repairOcrDigits(String line) {
        if (!OCR_CHEAP_TEST.matcher(line).find()) {
            return line;
        }
        return OCR_RUN.matcher(line).replaceAll(m -> {
            String run = m.group();
            long digits = run.chars().filter(c -> c >= '0' && c <= '9').count();
            long letters = run.chars().filter(c -> "OolI".indexOf(c) >= 0).count();
            return Matcher.quoteReplacement(letters > 0 && letters <= 2 && digits >= 6
                ? run.replace('O', '0').replace('o', '0').replace('l', '1').replace('I', '1') : run);
        });
    }

    private static final String NAME_TRIM = " \t.,:;-–—";

    /**
     * What a line says besides its phone, when that could be a name: "SMITH, John ........ 555-123-4567" gives
     * "SMITH, John". Null when nothing is left or what is left has a digit ("555-123-4567 ext. 12").
     */
    static String nameBesidePhone(String line, String phone) {
        int offset = line.indexOf(phone);
        if (offset < 0) {
            return null;
        }
        String rest = SingleLineEntrySplitter.trimChars(
            line.substring(0, offset) + " " + line.substring(offset + phone.length()), NAME_TRIM);
        return LETTER.matcher(rest).find() && !DIGIT.matcher(rest).find() ? rest : null;
    }

    static final Pattern LETTER = Pattern.compile("\\p{L}");
    static final Pattern DIGIT = Pattern.compile("\\d");

    /** True when {@code text}, trimmed, is nothing but a single phone match (so it is not also a street). */
    static boolean isOnlyAPhone(String text, String language) {
        String trimmed = text.trim();
        if (trimmed.isEmpty()) {
            return false;
        }
        Matcher m = regexFor(language).matcher(trimmed);
        return m.find() && m.group().equals(trimmed);
    }
}


/**
 * A street with no street-type word ("9 Wexford Close", "8 Clos Saint-Éloi"): the whole line is a house number
 * followed only by words. Same three shapes as the PHP StreetFallback.
 */
final class StreetFallback {
    private StreetFallback() {
    }

    private static final String WORD = "\\p{L}[\\p{L}\\p{M}'’.\\-]*";
    private static final int FLAGS = Pattern.CASE_INSENSITIVE | Pattern.UNICODE_CASE;
    private static final Pattern NUMBER_FIRST = Pattern.compile("\\d{1,5}[a-z]?\\s+" + WORD + "(?:\\s+" + WORD + ")*", FLAGS);
    private static final Pattern NUMBER_LAST = Pattern.compile(WORD + "(?:\\s+" + WORD + ")*\\s+\\d{1,5}[a-z]?", FLAGS);
    private static final Pattern DWELLING = Pattern.compile(
        "(?:apt|apartment|unit|flat|suite|ste|bldg|building)\\.?\\s*#?\\d{1,5}[a-z]?"
            + "(?:\\s+[\\p{L}\\d#][\\p{L}\\p{M}\\d'’.\\-#]*)*", FLAGS);

    static String numberFirst(String line) {
        String trimmed = line.trim();
        return NUMBER_FIRST.matcher(trimmed).matches() ? trimmed : null;
    }

    /** "Los Aromos 123", "Travessa do Carmo 5": only words, then the house number. */
    static String numberLast(String line) {
        String trimmed = line.trim();
        return NUMBER_LAST.matcher(trimmed).matches() ? trimmed : null;
    }

    /** "Apt 4 Building 7", "Unit 3", "Flat 2B Rose Court": an English address that opens with the dwelling. */
    static String dwelling(String line) {
        String trimmed = line.trim();
        return DWELLING.matcher(trimmed).matches() ? trimmed : null;
    }
}


/**
 * What counts as a street, per language (same rules as the PHP StreetRules): strong (a street word, or a house
 * number first and only words after it) or weak (the house number last, or a dwelling line, which only count
 * once the record's name is read).
 */
final class StreetRules {
    private StreetRules() {
    }

    static final List<String> SUPPORTED_LANGUAGES = List.of("es", "en", "fr", "pt", "de", "it");
    // Languages that write the house number after the street ("Calle Mayor 12", "Via Roma 10").
    static final Set<String> NUMBER_LAST_LANGUAGES = Set.of("es", "pt", "it", "de");

    private static final Map<String, List<String>> MARKERS = Map.of(
        "es", List.of("calle", "c/", "avenida", "av", "plaza", "pasaje", "camino", "ruta", "carrera"),
        "en", List.of("street", "st", "avenue", "ave", "road", "rd", "drive", "dr", "lane", "ln",
                      "boulevard", "blvd", "circle", "cir"),
        "fr", List.of("rue", "avenue", "allée", "place", "boulevard", "bd", "cours", "square"),
        "pt", List.of("rua", "avenida", "av", "praça", "alameda", "estrada", "largo"),
        "de", List.of("ring", "hof"),
        "it", List.of("via", "viale", "corso", "piazza", "largo", "strada")
    );
    // German compounds glue the street type onto the name ("Hauptstraße"), so these match as word endings.
    static final Map<String, List<String>> SUFFIXES =
        Map.of("de", List.of("straße", "strasse", "allee", "weg", "platz"));
    // Street words that are also the usual abbreviation opening a name ("Dr." doctor, "St." saint): they only
    // make a street when the line has a digit or they are not its first word.
    private static final Map<String, List<String>> NAME_ABBREVIATIONS = Map.of("en", List.of("dr", "st"));

    private static final int FLAGS = Pattern.CASE_INSENSITIVE | Pattern.UNICODE_CASE;
    private static final String BEFORE = "(?<![\\p{L}\\d])";
    private static final String AFTER = "(?![\\p{L}\\d])";
    private static final Map<String, Pattern> PATTERNS = new HashMap<>();
    private static final Map<String, Optional<Pattern>> ABBREVIATIONS = new HashMap<>();

    private static String alternation(List<String> words) {
        List<String> quoted = new ArrayList<>();
        words.forEach(w -> quoted.add(Pattern.quote(w)));
        return String.join("|", quoted);
    }

    /** The street words of {@code language} as a regex; the NAME_ABBREVIATIONS are not in it. */
    static synchronized Pattern pattern(String language) {
        Pattern cached = PATTERNS.get(language);
        if (cached == null) {
            if (!MARKERS.containsKey(language)) {
                throw new IllegalArgumentException("Unsupported language for street pattern: " + language
                    + ". Supported languages: " + String.join(", ", SUPPORTED_LANGUAGES));
            }
            List<String> abbreviations = NAME_ABBREVIATIONS.getOrDefault(language, List.of());
            List<String> markers = new ArrayList<>();
            MARKERS.get(language).forEach(m -> {
                if (!abbreviations.contains(m)) {
                    markers.add(m);
                }
            });
            // Not \b: "c/" ends in a slash, and \b only sees a word edge after a letter or digit.
            StringBuilder body = new StringBuilder(BEFORE + "(?:" + alternation(markers) + ")" + AFTER);
            List<String> suffixes = SUFFIXES.getOrDefault(language, List.of());
            if (!suffixes.isEmpty()) {
                body.append("|(?:").append(alternation(suffixes)).append(")\\b");
            }
            cached = Pattern.compile(body.toString(), FLAGS | Pattern.UNICODE_CHARACTER_CLASS);
            PATTERNS.put(language, cached);
        }
        return cached;
    }

    private static synchronized Pattern abbreviationPattern(String language) {
        return ABBREVIATIONS.computeIfAbsent(language, l -> {
            List<String> words = NAME_ABBREVIATIONS.getOrDefault(l, List.of());
            return words.isEmpty()
                ? Optional.<Pattern>empty()
                : Optional.of(Pattern.compile(BEFORE + "(?:" + alternation(words) + ")" + AFTER, FLAGS));
        }).orElse(null);
    }

    /** Whether the line has a street word (a strong street). */
    static boolean hasStreetWord(String line, String language) {
        if (pattern(language).matcher(line).find()) {
            return true;
        }
        Pattern abbreviation = abbreviationPattern(language);
        if (abbreviation == null) {
            return false;
        }
        Matcher m = abbreviation.matcher(line);
        List<Integer> starts = new ArrayList<>();
        while (m.find()) {
            starts.add(m.start());
        }
        if (starts.isEmpty()) {
            return false;
        }
        if (PhonePattern.DIGIT.matcher(line).find()) {
            return true;
        }
        for (int start : starts) {
            if (!line.substring(0, start).trim().isEmpty()) {
                return true;
            }
        }
        return false;
    }

    /** The line, when it is a street with a street word or a house number first; null otherwise. */
    static String strong(String line, String language) {
        if (hasStreetWord(line, language)) {
            return line;
        }
        if (PhonePattern.isOnlyAPhone(line, language)) {
            return null;
        }
        return StreetFallback.numberFirst(line);
    }

    /**
     * An address that only counts once the record's name is read: a street with no street word in a language
     * that writes the house number last ("Los Aromos 123"); a dwelling line in English.
     */
    static String afterName(String line, String language) {
        if ("en".equals(language)) {
            return StreetFallback.dwelling(line);
        }
        return NUMBER_LAST_LANGUAGES.contains(language) ? StreetFallback.numberLast(line) : null;
    }

    private static final Pattern WORD_TAIL = Pattern.compile("[\\p{L}\\p{M}'’.\\-]*$");
    private static final Pattern NON_SPACE = Pattern.compile("\\S+");

    /**
     * Where the street starts in the text before a number-last house number ("GARCÍA Juan Calle Mayor" gives
     * the offset of "Calle"): the first street word after the first two words, which belong to the name. A
     * street word that is a suffix ("Hauptstraße") starts the street at the start of its word. Null if none.
     */
    static Integer streetWordOffset(String text, String language) {
        Matcher m = pattern(language).matcher(text);
        while (m.find()) {
            Matcher tail = WORD_TAIL.matcher(text.substring(0, m.start()));
            int offset = m.start() - (tail.find() ? tail.group().length() : 0);
            int words = 0;
            Matcher w = NON_SPACE.matcher(text.substring(0, offset));
            while (w.find()) {
                words++;
            }
            if (words >= 2) {
                return offset;
            }
        }
        return null;
    }
}


/**
 * What one line of a block says, worked out once: its phone, its street (with or without the record's name
 * already read) and, for a phone line, the name beside the phone.
 */
record LineFacts(String line, String phone, String strongStreet, String afterNameStreet, String nameBesidePhone) {

    static LineFacts of(String line, String language, Pattern phoneRegex) {
        String phone = PhonePattern.find(line, phoneRegex);
        return new LineFacts(
            line, phone,
            StreetRules.strong(line, language),
            StreetRules.afterName(line, language),
            phone == null ? null : PhonePattern.nameBesidePhone(PhonePattern.repairOcrDigits(line), phone));
    }

    /** The street this line gives, depending on whether the record's name has been read. */
    String street(boolean afterName) {
        return strongStreet != null ? strongStreet : (afterName ? afterNameStreet : null);
    }

    /** The name this line can give: a line of text, or what a phone line has beside its phone. */
    String name(boolean afterName) {
        if (street(afterName) != null) {
            return null;
        }
        return phone == null ? line : nameBesidePhone;
    }
}


/**
 * Reads a block of lines (between two blank lines) into records, looking at each line once (LineFacts). Same
 * rules as the PHP BlockReader: words the scan cut with a hyphen are put back together; a block can hold several
 * records, a new one starting at a name when a street follows it right away; the street is the first one unless
 * it was weak and a strong one comes later; any other text after the name is a note.
 */
final class BlockReader {
    private BlockReader() {
    }

    /** One record of a block, with the offset of its first line in the block. */
    static final class Record {
        final int offset;
        String name;
        String phone;
        String street;
        final List<String> notes = new ArrayList<>();

        Record(int offset) {
            this.offset = offset;
        }
    }

    static List<Record> read(List<String> lines, Function<String, LineFacts> classify) {
        List<String> joined = new ArrayList<>();
        List<Integer> origin = new ArrayList<>();
        joinHyphenatedWords(lines, joined, origin);
        List<LineFacts> facts = new ArrayList<>();
        joined.forEach(l -> facts.add(classify.apply(l)));

        List<Record> records = new ArrayList<>();
        Record record = new Record(0);
        boolean strongStreet = false;

        for (int i = 0; i < facts.size(); i++) {
            LineFacts line = facts.get(i);
            boolean hasName = record.name != null;

            if (hasName && record.street != null && line.name(true) != null
                    && i + 1 < facts.size() && facts.get(i + 1).street(true) != null) {
                records.add(record);
                record = new Record(origin.get(i));
                strongStreet = false;
                hasName = false;
            }

            if (record.phone == null && line.phone() != null) {
                record.phone = line.phone();
            }

            String street = line.street(hasName);
            if (street != null && (record.street == null || (!strongStreet && line.strongStreet() != null))) {
                record.street = street;
                strongStreet = line.strongStreet() != null;
            }

            String name = line.name(hasName);
            if (!hasName && name != null) {
                record.name = name;
            } else if (hasName && line.phone() == null && street == null) {
                record.notes.add(line.line());
            }
        }

        records.add(record);
        return records;
    }

    private static final Pattern ENDS_WITH_LETTER_AND_HYPHEN = Pattern.compile("\\p{L}-$");
    private static final Pattern STARTS_WITH_LETTER = Pattern.compile("^\\p{L}");

    /** "WASHING- / TON, George" becomes "WASHINGTON, George" (a name cut at its own hyphen loses it). */
    private static void joinHyphenatedWords(List<String> lines, List<String> joined, List<Integer> origin) {
        for (int i = 0; i < lines.size(); i++) {
            String line = lines.get(i);
            int last = joined.size() - 1;
            if (last >= 0 && ENDS_WITH_LETTER_AND_HYPHEN.matcher(joined.get(last)).find()
                    && STARTS_WITH_LETTER.matcher(line).find()) {
                String previous = joined.get(last);
                joined.set(last, previous.substring(0, previous.length() - 1) + line);
                continue;
            }
            joined.add(line);
            origin.add(i);
        }
    }
}


/**
 * Splits a single "NAME ... STREET ... PHONE" line, the layout most historical directories use: by delimited
 * fields (commas, dot leaders, tabs, runs of spaces or dashes) or, with none, at the first digit once the phone
 * is out. Same strategies as the PHP SingleLineEntrySplitter.
 */
final class SingleLineEntrySplitter {
    private SingleLineEntrySplitter() {
    }

    /** name, street and phone (null) of one record. */
    record Split(String name, String street, String phone) {
    }

    private static final String LEADER_CHARS = " \t\n\r\0\u000b.,-–—";
    private static final String TRIM_CHARS = " \n\r\t\u000b\0";
    // At least 2 dots for a dot leader (so "Mrs. John" and "St. Louis" do not split) and at least 2 spaces or dashes.
    private static final Pattern FIELD_DELIMITER = Pattern.compile("\\s*,\\s*|(?:\\.\\s?){2,}|\\t+|[ ]{2,}|[-–—]{2,}");
    private static final Pattern STARTS_WITH_DIGIT = Pattern.compile("^\\d");
    private static final Pattern FROM_FIRST_DIGIT = Pattern.compile("\\d.*", Pattern.DOTALL);

    static String trimChars(String text, String chars) {
        int start = 0;
        int end = text.length();
        while (start < end && chars.indexOf(text.charAt(start)) >= 0) {
            start++;
        }
        while (end > start && chars.indexOf(text.charAt(end - 1)) >= 0) {
            end--;
        }
        return text.substring(start, end);
    }

    static Split split(String line, Predicate<String> looksLikeStreet, int surnameWordCount,
                       String language, Function<String, Integer> streetStart) {
        // A phone the scan misread ("555-l23-4567") would otherwise pass for a street starting with a digit.
        line = PhonePattern.repairOcrDigits(line);
        List<String> fields = new ArrayList<>();
        for (String field : FIELD_DELIMITER.split(line)) {
            if (!field.isEmpty()) {
                fields.add(trimChars(field, TRIM_CHARS));
            }
        }

        if (fields.size() >= 2) {
            Split result = splitByFields(fields, looksLikeStreet, language);
            if (result != null) {
                return result;
            }
        }

        Split result = splitByDigitBoundary(line, looksLikeStreet, language, streetStart);
        return result == null ? null
            : new Split(assumeSurnameFirst(result.name(), surnameWordCount), result.street(), result.phone());
    }

    /** "SMITH John" -> "SMITH, John"; leaves an already-comma'd or too-short name alone. */
    private static String assumeSurnameFirst(String name, int surnameWordCount) {
        if (name.contains(",")) {
            return name;
        }
        String[] words = name.trim().split("\\s+");
        if (words.length <= surnameWordCount) {
            return name;
        }
        return String.join(" ", Arrays.copyOfRange(words, 0, surnameWordCount)) + ", "
            + String.join(" ", Arrays.copyOfRange(words, surnameWordCount, words.length));
    }

    private static Split splitByFields(List<String> fields, Predicate<String> looksLikeStreet, String language) {
        String phone = null;
        for (String field : fields) {
            if (PhonePattern.isOnlyAPhone(field, language)) {
                phone = field;
                break;
            }
        }

        int streetIndex = -1;
        for (int i = 0; i < fields.size(); i++) {
            if (!fields.get(i).equals(phone) && looksLikeStreet.test(fields.get(i))) {
                streetIndex = i;
                break;
            }
        }
        // No field alone reads as a street by its marker word: the first that at least starts with a digit.
        if (streetIndex < 0) {
            for (int i = 0; i < fields.size(); i++) {
                if (!fields.get(i).equals(phone) && STARTS_WITH_DIGIT.matcher(fields.get(i)).find()) {
                    streetIndex = i;
                    break;
                }
            }
        }

        // A street field is only meaningful with a name field before it in the same record.
        if (streetIndex <= 0) {
            return null;
        }

        List<String> nameFields = new ArrayList<>();
        for (int i = 0; i < streetIndex; i++) {
            if (!fields.get(i).equals(phone)) {
                nameFields.add(fields.get(i));
            }
        }
        String name = String.join(", ", nameFields);
        String street = fields.get(streetIndex);

        // A name with no letters is a house number cut off by its comma ("12, rue de la Paix"), not a name.
        if (!PhonePattern.LETTER.matcher(name).find() || street.isEmpty()) {
            return null;
        }
        return new Split(name, street, phone);
    }

    private static Split splitByDigitBoundary(String line, Predicate<String> looksLikeStreet, String language,
                                              Function<String, Integer> streetStart) {
        String rest = line;
        String phone = null;

        Matcher match = PhonePattern.regexFor(language).matcher(line);
        if (match.find()) {
            phone = match.group();
            // A line that opens with the phone ("555-123-4567 ext. 12") is the end of a record, not a whole one.
            if (!PhonePattern.LETTER.matcher(line.substring(0, match.start())).find()) {
                return null;
            }
            rest = line.substring(0, match.start()) + line.substring(match.end());
        }

        Matcher tail = FROM_FIRST_DIGIT.matcher(rest);
        if (!tail.find()) {
            return null;
        }

        String name = trimChars(rest.substring(0, tail.start()), LEADER_CHARS);
        String street = trimChars(tail.group(), LEADER_CHARS);

        Integer cut = streetStart != null && !name.isEmpty() ? streetStart.apply(name) : null;
        if (cut != null && cut > 0 && cut < name.length()) {
            street = name.substring(cut) + " " + street;
            name = trimChars(name.substring(0, cut), LEADER_CHARS);
        }

        if (name.isEmpty() || street.isEmpty()) {
            return null;
        }

        // With no phone and no recognized street word, a bare "word + number" is too ambiguous to split out.
        if (phone == null && !looksLikeStreet.test(street)) {
            return null;
        }
        return new Split(name, street, phone);
    }
}


/**
 * Multi-language phone directory parser (same parsing as the PHP MultiLanguagePhoneDirectoryParser: one-line
 * entries, blocks of lines, businesses and persons).
 */
class MultiLanguagePhoneDirectoryParser {

    // Only vocabulary distinctive enough to override English, the baseline language; words that are also
    // English ("plaza", "avenue", "via") would otherwise misdetect an English directory.
    private static final Map<String, List<String>> DETECTION_MARKERS =
        Map.of(
            "es", List.of("calle", "avenida", "pasaje", "camino", "ruta", "carrera"),
            "fr", List.of("rue", "allée", "cours"),
            "pt", List.of("rua", "avenida", "praça", "alameda", "estrada", "largo"),
            "de", List.of(),
            "it", List.of("viale", "corso", "piazza", "largo", "strada")
        );

    // Fixed order so ties resolve the same way on every run (Map.of has no defined iteration order).
    private static final List<String> DETECTION_ORDER = List.of("es", "fr", "pt", "de", "it");

    // Words that name a kind of business, or a family firm ("Smith & Sons", "Hnos."), and that almost nobody has
    // for a surname. A word many people carry as a surname (Baker, Miller, Bank...) does not belong here.
    private static final Map<String, List<String>> ENTITY_TYPE_MARKERS = Map.of(
        "es", List.of("spa", "srl", "sa", "ltda", "inc", "comercial", "empresa", "negocio", "tienda", "restaurante", "hotel", "banco", "farmacia", "hospital", "hnos", "hermanos", "hijos", "sucesores", "taller", "ferretería", "clínica", "panadería", "carnicería", "librería", "zapatería"),
        "en", List.of("corp", "inc", "ltd", "llc", "company", "store", "shop", "restaurant", "hotel", "bank", "pharmacy", "hospital", "sons", "bros", "associates", "enterprises", "industries", "services", "plumbing", "garage", "bakery", "hardware", "clinic", "pub", "motel"),
        "fr", List.of("sarl", "sas", "eirl", "eurl", "magasin", "restaurant", "hôtel", "banque", "pharmacie", "hôpital", "frères", "boulangerie", "boucherie", "garage", "clinique", "quincaillerie", "cabinet"),
        "pt", List.of("ltda", "lda", "eireli", "empresa", "comercial", "loja", "padaria", "restaurante", "hotel", "banco", "farmácia", "hospital", "irmãos", "filhos", "oficina", "clínica", "mercearia", "ferragens", "sapataria"),
        "de", List.of("gmbh", "ag", "kg", "ohg", "apotheke", "bäckerei", "gasthaus", "restaurant", "hotel", "bank", "krankenhaus", "gebrüder", "söhne", "werkstatt", "metzgerei", "tischlerei", "klinik"),
        "it", List.of("srl", "spa", "snc", "sas", "negozio", "panificio", "trattoria", "ristorante", "albergo", "hotel", "banca", "farmacia", "ospedale", "fratelli", "figli", "officina", "macelleria", "ferramenta", "pasticceria", "pizzeria", "clinica")
    );

    private static final Pattern SEPARATOR = Pattern.compile("^(?:[-=_*]\\s*){2,}$");
    private static final Pattern GIVEN_NAME = Pattern.compile("^\\p{L}[\\p{L}'. -]*$");
    private static final Map<String, Pattern> ENTITY_PATTERNS = new HashMap<>();

    private final String countryCode;
    private final List<Map<String, Object>> entries;
    private final List<Map<String, Object>> errors;
    private String detectedLanguage;

    public MultiLanguagePhoneDirectoryParser() {
        this("US");
    }

    public MultiLanguagePhoneDirectoryParser(String countryCode) {
        this.countryCode = countryCode.toUpperCase();
        this.entries = new ArrayList<>();
        this.errors = new ArrayList<>();
        this.detectedLanguage = "en";
    }

    /** Whole-word matcher for $words plus word-ending $suffixes; null when both are empty. */
    private static Pattern wordsPattern(List<String> words, List<String> suffixes) {
        List<String> parts = new ArrayList<>();
        if (!words.isEmpty()) {
            List<String> quoted = new ArrayList<>();
            words.forEach(w -> quoted.add(Pattern.quote(w)));
            parts.add("\\b(?:" + String.join("|", quoted) + ")\\b");
        }
        suffixes.forEach(s -> parts.add(Pattern.quote(s) + "\\b"));
        return parts.isEmpty()
            ? null
            : Pattern.compile(String.join("|", parts),
                Pattern.CASE_INSENSITIVE | Pattern.UNICODE_CASE | Pattern.UNICODE_CHARACTER_CLASS);
    }

    // "Via Roma 10" opens the line and ends with the number, a shape English doesn't use ("12 Via Lane"),
    // so "via" counts as Italian only there.
    private static final Map<String, Pattern> DETECTION_LINES = Map.of(
        "it", Pattern.compile(
            "^[ \\t]*via\\s+\\p{L}[\\p{L}\\p{M}'’.\\-]*(?:\\s+\\p{L}[\\p{L}\\p{M}'’.\\-]*)*,?\\s+\\d{1,5}[a-z]?\\b",
            Pattern.CASE_INSENSITIVE | Pattern.UNICODE_CASE | Pattern.MULTILINE
        )
    );

    public String detectLanguage(String content) {
        String best = "en";
        int bestScore = 0;

        for (String lang : DETECTION_ORDER) {
            Pattern p = wordsPattern(DETECTION_MARKERS.get(lang), StreetRules.SUFFIXES.getOrDefault(lang, List.of()));
            if (p == null) {
                continue;
            }
            int score = (int) p.matcher(content).results().count();
            if (DETECTION_LINES.containsKey(lang)) {
                score += (int) DETECTION_LINES.get(lang).matcher(content).results().count();
            }
            if (score > bestScore) {
                best = lang;
                bestScore = score;
            }
        }

        return best;
    }

    public List<Map<String, Object>> parseContent(String content, String language) {
        if (language == null) {
            language = detectLanguage(content);
        }
        if (!StreetRules.SUPPORTED_LANGUAGES.contains(language)) {
            throw new IllegalArgumentException("Unsupported language: " + language + ". Supported languages: "
                + String.join(", ", StreetRules.SUPPORTED_LANGUAGES));
        }
        detectedLanguage = language;
        entries.clear();
        errors.clear();
        Pattern phoneRegex = PhonePattern.regexFor(language);

        String[] lines = content.split("\n", -1);
        List<String> buffer = new ArrayList<>();
        int bufferStart = 0;
        final String lang = language;
        int surnameCount = PersonName.surnameCount(lang);
        Function<String, Integer> streetStart = StreetRules.NUMBER_LAST_LANGUAGES.contains(lang)
            ? before -> StreetRules.streetWordOffset(before, lang) : null;

        // Line numbers are 1-based positions in the original content, so errors point at the real line.
        for (int i = 0; i < lines.length; i++) {
            String line = lines[i].trim();

            if (line.isEmpty() || SEPARATOR.matcher(line).matches()) {
                if (!buffer.isEmpty()) {
                    processBuffer(new ArrayList<>(buffer), bufferStart, lang, phoneRegex);
                    buffer.clear();
                }
                continue;
            }

            // Most historical directories print one full record per line: try that shape first. Only a street
            // word counts as a street there (the number-first fallback would turn "Apt 4 Building 7" into a record).
            SingleLineEntrySplitter.Split single = SingleLineEntrySplitter.split(
                line, street -> StreetRules.hasStreetWord(street, lang), surnameCount, lang, streetStart);
            if (single != null) {
                if (!buffer.isEmpty()) {
                    processBuffer(new ArrayList<>(buffer), bufferStart, lang, phoneRegex);
                    buffer.clear();
                }
                finalizeEntry(single.name(), single.phone(), single.street(),
                    businessTypeOfName(single.name(), lang), i + 1, lang);
                continue;
            }

            if (buffer.isEmpty()) {
                bufferStart = i + 1;
            }
            buffer.add(line);
        }

        if (!buffer.isEmpty()) {
            processBuffer(buffer, bufferStart, lang, phoneRegex);
        }

        return entries;
    }

    private void processBuffer(List<String> lines, int startLine, String language, Pattern phoneRegex) {
        for (BlockReader.Record record : BlockReader.read(lines, l -> LineFacts.of(l, language, phoneRegex))) {
            // Only the name and the notes can say it is a business: "12 Bank Street" says where someone lives.
            String business = record.name != null ? businessTypeOfName(record.name, language) : null;
            for (String note : record.notes) {
                if (business == null) {
                    business = extractBusinessType(note, language);
                }
            }
            finalizeEntry(record.name, record.phone, record.street, business, startLine + record.offset, language);
        }
    }

    private void finalizeEntry(String name, String phone, String street, String type, int startLine, String language) {
        if (name == null || name.isEmpty() || street == null || street.isEmpty()) {
            Map<String, Object> error = new HashMap<>();
            error.put("line", startLine);
            error.put("reason", "Missing name or street");
            Map<String, Object> data = new HashMap<>();
            data.put("name", name);
            data.put("phone", phone);
            data.put("street", street);
            error.put("data", data);
            errors.add(error);
            return;
        }

        try {
            Map<String, Object> entry = new HashMap<>();
            Map<String, Object> location = new GeoLocation(countryCode, street).toMap();
            boolean juridical = (type != null && !type.isEmpty()) || businessTypeOfName(name, language) != null;
            if (juridical) {
                entry.put("type", "juridical");
                entry.put("business_name", name);
                entry.put("business_type", type);
            } else {
                entry.put("type", "natural");
                entry.put("person_name", new PersonName(name, language).toMap());
            }
            entry.put("location", location);
            entry.put("phone", phone);
            entry.put("record_date", LocalDateTime.now().toString());
            entry.put("language", language);
            entries.add(entry);
        } catch (Exception e) {
            Map<String, Object> error = new HashMap<>();
            error.put("line", startLine);
            error.put("reason", e.getMessage());
            errors.add(error);
        }
    }

    private static synchronized String extractBusinessType(String line, String language) {
        List<String> markers = ENTITY_TYPE_MARKERS.getOrDefault(language, List.of());
        if (markers.isEmpty()) {
            return null;
        }
        Pattern pattern = ENTITY_PATTERNS.computeIfAbsent(language, l -> {
            List<String> quoted = new ArrayList<>();
            markers.forEach(m -> quoted.add(Pattern.quote(m)));
            return Pattern.compile("\\b(" + String.join("|", quoted) + ")\\b",
                Pattern.CASE_INSENSITIVE | Pattern.UNICODE_CASE | Pattern.UNICODE_CHARACTER_CLASS);
        });
        Matcher match = pattern.matcher(line);
        return match.find() ? match.group(1).toLowerCase(Locale.ROOT) : null;
    }

    /**
     * The business word in a name, unless the name is a surname followed by a given name: "BANK, Henry" is a
     * person whose surname happens to be a business word. Only the exact shape "&lt;one word&gt;, &lt;given name&gt;"
     * is spared, with no business word in the given-name part: "BANK OF AMERICA, N.A." stays a business.
     */
    private static String businessTypeOfName(String name, String language) {
        String type = extractBusinessType(name, language);
        if (type == null) {
            return null;
        }
        String[] parts = name.split(",", -1);
        boolean surnameAndGivenName = parts.length == 2
            && parts[0].trim().toLowerCase(Locale.ROOT).equals(type)
            && GIVEN_NAME.matcher(parts[1].trim()).matches()
            && extractBusinessType(parts[1].trim(), language) == null;
        return surnameAndGivenName ? null : type;
    }

    public List<Map<String, Object>> getEntries() {
        return new ArrayList<>(entries);
    }

    public List<Map<String, Object>> getErrors() {
        return new ArrayList<>(errors);
    }

    public String getDetectedLanguage() {
        return detectedLanguage;
    }
}


/**
 * Historical phone directory catalog.
 */
class PhoneDirectoryCatalog {
    private Map<String, Map<String, Object>> directories;

    public PhoneDirectoryCatalog() {
        directories = new HashMap<>();
        loadDirectories();
    }

    private void loadDirectories() {
        addDirectory("us_1878", "New York Telephone Directory", 1878, "US", 3000);
        addDirectory("us_1950", "AT&T National Directory", 1950, "US", 2000000);
        addDirectory("gb_1880", "London Telephone Directory", 1880, "GB", 5000);
        addDirectory("fr_1960", "Annuaire National - France", 1960, "FR", 3000000);
        addDirectory("de_1970", "Deutsche Telefonverzeichnis", 1970, "DE", 5000000);
        addDirectory("es_1975", "Guía Telefónica - España", 1975, "ES", 4000000);
    }

    private void addDirectory(String id, String title, int year,
                            String country, int entries) {
        Map<String, Object> dir = new HashMap<>();
        dir.put("title", title);
        dir.put("year", year);
        dir.put("country", country);
        dir.put("estimated_entries", entries);
        directories.put(id, dir);
    }

    public Map<String, Map<String, Object>> getAll() {
        return new HashMap<>(directories);
    }

    public Map<String, Object> getStatistics() {
        int totalEntries = directories.values().stream()
            .mapToInt(d -> (Integer) d.get("estimated_entries"))
            .sum();

        Set<Integer> years = new HashSet<>();
        Set<String> countries = new HashSet<>();

        directories.values().forEach(d -> {
            years.add((Integer) d.get("year"));
            countries.add((String) d.get("country"));
        });

        Map<String, Object> stats = new HashMap<>();
        stats.put("total_directories", directories.size());
        stats.put("total_countries", countries.size());
        stats.put("total_years", years.size());
        stats.put("total_entries", totalEntries);
        stats.put("earliest_year", Collections.min(years));
        stats.put("latest_year", Collections.max(years));

        return stats;
    }
}


/**
 * Example usage and entry point.
 */
public class PhoneDirectoryParser {

    public static void main(String[] args) {
        // Create parser
        MultiLanguagePhoneDirectoryParser parser =
            new MultiLanguagePhoneDirectoryParser();

        // Sample content
        String sampleContent = "García, Juan José\n" +
                              "Calle Principal 123\n" +
                              "555-1234567\n\n" +
                              "Smith, John Robert\n" +
                              "123 Main Street\n" +
                              "555-9876543";

        // Parse content
        List<Map<String, Object>> entries = parser.parseContent(sampleContent, null);

        System.out.println("Parsed " + entries.size() + " entries");
        System.out.println("Detected language: " + parser.getDetectedLanguage());
        System.out.println("Errors: " + parser.getErrors().size());

        // Display entries
        for (Map<String, Object> entry : entries) {
            Map<String, Object> person = (Map<String, Object>) entry.get("person_name");
            System.out.println("\nName: " + person.get("formatted"));
            System.out.println("Phone: " + entry.get("phone"));
        }

        // Test catalog
        PhoneDirectoryCatalog catalog = new PhoneDirectoryCatalog();
        Map<String, Object> stats = catalog.getStatistics();
        System.out.println("\n--- Catalog Statistics ---");
        System.out.println("Total directories: " + stats.get("total_directories"));
        System.out.println("Total entries: " + stats.get("total_entries"));
        System.out.println("Year range: " + stats.get("earliest_year") +
                          " - " + stats.get("latest_year"));
    }
}
