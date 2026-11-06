/**
 * Phone Directory Parser - Java Implementation
 * Genealogical phone directory parsing with multi-language support
 */

import java.sql.*;
import java.time.LocalDateTime;
import java.util.*;
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
 * Multi-language phone directory parser.
 */
class MultiLanguagePhoneDirectoryParser {

    private static final Map<String, List<String>> STREET_MARKERS =
        Map.ofEntries(
            Map.entry("es", List.of("calle", "avenida", "av", "plaza", "pasaje", "camino", "ruta", "carrera")),
            Map.entry("en", List.of("street", "st", "avenue", "ave", "road", "rd", "drive", "dr",
                                    "lane", "ln", "boulevard", "blvd", "circle", "cir")),
            Map.entry("fr", List.of("rue", "avenue", "allée", "place", "boulevard", "bd", "cours", "square")),
            Map.entry("pt", List.of("rua", "avenida", "av", "praça", "alameda", "estrada", "largo")),
            Map.entry("de", List.of("ring", "hof")),
            Map.entry("it", List.of("via", "viale", "corso", "piazza", "largo", "strada"))
        );

    // German compounds glue the street type onto the name ("Hauptstraße"), so these match as word endings.
    private static final Map<String, List<String>> STREET_SUFFIXES =
        Map.of("de", List.of("straße", "strasse", "allee", "weg", "platz"));

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

    private static final Pattern SEPARATOR = Pattern.compile("^(?:[-=_*]\\s*){2,}$");
    private static final Pattern NUMBER_FIRST_STREET = Pattern.compile(
        "\\d{1,5}[a-zA-Z]?\\s+\\p{L}[\\p{L}\\p{M}'’.\\-]*(?:\\s+\\p{L}[\\p{L}\\p{M}'’.\\-]*)*"
    );

    private static final Pattern NUMBER_LAST_STREET = Pattern.compile(
        "\\p{L}[\\p{L}\\p{M}'’.\\-]*(?:\\s+\\p{L}[\\p{L}\\p{M}'’.\\-]*)*\\s+\\d{1,5}[a-zA-Z]?"
    );
    // Same as the PHP StreetFallback::dwelling: "Apt 4 Building 7", only once the name is read, in English.
    private static final Pattern DWELLING = Pattern.compile(
        "(?:apt|apartment|unit|flat|suite|ste|bldg|building)\\.?\\s*#?\\d{1,5}[a-z]?(?:\\s+[\\p{L}\\d#][\\p{L}\\p{M}\\d'’.\\-#]*)*",
        Pattern.CASE_INSENSITIVE | Pattern.UNICODE_CASE
    );
    // Languages that write the house number after the street ("Travessa do Carmo 5").
    private static final Set<String> NUMBER_LAST_LANGUAGES = Set.of("es", "pt", "it", "de");

    // Same as PhonePattern::REGEX: digit groups (555-123-4567, 5551234567) and the named exchange of early
    // directories ("BUtterfield 8-4521"), which needs a separator on both sides of the word.
    private static final String BASE_PHONE =
        "\\b[A-Za-z]{3,}[ .-]\\d[ -]\\d{4}\\b|\\b\\d{3}[-.\\s]?\\d{3}[-.\\s]?\\d{4}\\b|\\b\\d{10}\\b";

    // Same as PhonePattern::LANGUAGE_FORMATS: the formats of each language's countries, kept per language
    // because the looser ones would read a house number as part of a phone in another language's directory.
    private static final Map<String, List<String>> LANGUAGE_PHONE_FORMATS = Map.of(
        "fr", List.of("(?:\\+33[ .]?[1-9]|0[1-9])(?:[ .]?\\d{2}){4}"),
        "de", List.of("(?:\\+49[ ]?[1-9]\\d{1,3}|\\(0\\d{2,4}\\)|0\\d{2,4})[ /-]?\\d{3,8}(?:-\\d{1,4})?"),
        "it", List.of("(?:\\+39[ ]?)?0\\d{1,3}[ /-]?\\d{4,8}"),
        "pt", List.of("(?:\\+351[ ]?)?(?:[2-9]\\d[ ]\\d{3}[ ]\\d{4}|[2-9]\\d{2}[ ]\\d{3}[ ]\\d{3})"),
        "es", List.of("(?:\\+34[ ]?)?[6-9]\\d[ ]\\d{3}(?:[ ]\\d{2}){2}",
                      "(?:\\(\\d{1,3}\\)[ ]?|\\d{1,3}[ -])\\d{3}[ -]\\d{3,4}")
    );
    // Not preceded by a digit or a "+", not followed by a separator and a digit.
    private static final String PHONE_BEFORE = "(?<![\\d+])";
    private static final String PHONE_AFTER = "(?![ ./-]?\\d)";
    private static final Map<String, Pattern> PHONE_REGEXES = new HashMap<>();

    /** The base formats plus those of {@code language}'s countries (English and unknown languages: the base). */
    static synchronized Pattern phoneRegex(String language) {
        String key = language != null && LANGUAGE_PHONE_FORMATS.containsKey(language) ? language : "";
        return PHONE_REGEXES.computeIfAbsent(key, k -> {
            StringBuilder regex = new StringBuilder(BASE_PHONE);
            for (String format : LANGUAGE_PHONE_FORMATS.getOrDefault(k, List.of())) {
                regex.append('|').append(PHONE_BEFORE).append("(?:").append(format).append(')').append(PHONE_AFTER);
            }
            return Pattern.compile(regex.toString());
        });
    }

    private static final Pattern OCR_CHEAP_TEST = Pattern.compile("\\d[ .\\-]?[OolI]|[OolI][ .\\-]?\\d");
    private static final Pattern OCR_RUN =
        Pattern.compile("(?<![\\p{L}\\d])[\\dOolI](?:[ .\\-]?[\\dOolI])+(?![\\p{L}\\d])");

    /**
     * Same as PhonePattern::repairOcrDigits: "l" or "I" for 1 and "O" or "o" for 0 inside what looks like a phone
     * number ("555-l23-4567" gives "555-123-4567"), only in a run with at least 6 real digits and at most 2 such
     * letters, so words ("Oil") and exchange names ("BUtterfield 8-4521") are left alone.
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

    /** The line's phone, also when the scan misread a digit as a letter ("555-l23-4567"); null if it has none. */
    static String phoneIn(String line, String language) {
        Pattern regex = phoneRegex(language);
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

    /**
     * What a line says besides its phone, when that could be a name: "SMITH, John ........ 555-123-4567" gives
     * "SMITH, John". Null when nothing is left or what is left has a digit ("555-123-4567 ext. 12").
     */
    static String nameBesidePhone(String line, String phone) {
        int offset = line.indexOf(phone);
        if (offset < 0) {
            return null;
        }
        String rest = (line.substring(0, offset) + " " + line.substring(offset + phone.length()))
            .replaceAll("^[ \\t.,:;\\-\\u2013\\u2014]+|[ \\t.,:;\\-\\u2013\\u2014]+$", "");
        return Pattern.compile("\\p{L}").matcher(rest).find() && !Pattern.compile("\\d").matcher(rest).find() ? rest : null;
    }

    private final String countryCode;
    private List<Map<String, Object>> entries;
    private List<Map<String, Object>> errors;
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

    // Same as the PHP LANGUAGE_DETECTION_LINES: "Via Roma 10" opens the line and ends with the number,
    // a shape English doesn't use ("12 Via Lane"), so "via" counts as Italian only there.
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
            Pattern p = wordsPattern(DETECTION_MARKERS.get(lang), STREET_SUFFIXES.getOrDefault(lang, List.of()));
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
        detectedLanguage = language;
        entries.clear();
        errors.clear();

        String[] lines = content.split("\n", -1);
        List<String> buffer = new ArrayList<>();
        int bufferStart = 0;

        // Line numbers are 1-based positions in the original content, so errors point at the real line.
        for (int i = 0; i < lines.length; i++) {
            String line = lines[i].trim();

            if (line.isEmpty() || SEPARATOR.matcher(line).matches()) {
                if (!buffer.isEmpty()) {
                    processBuffer(new ArrayList<>(buffer), bufferStart, language);
                    buffer.clear();
                }
            } else {
                if (buffer.isEmpty()) {
                    bufferStart = i + 1;
                }
                buffer.add(line);
            }
        }

        if (!buffer.isEmpty()) {
            processBuffer(buffer, bufferStart, language);
        }

        return entries;
    }

    private boolean isStreetLine(String line, String language, boolean afterName) {
        return extractStreet(line, language) != null || (afterName && afterNameStreet(line, language) != null);
    }

    // Same as the PHP afterNameStreet(): a dwelling line in English, a number-last street without a street word elsewhere.
    private String afterNameStreet(String line, String language) {
        String trimmed = line.trim();
        if ("en".equals(language)) {
            return DWELLING.matcher(trimmed).matches() ? trimmed : null;
        }
        return NUMBER_LAST_LANGUAGES.contains(language) && NUMBER_LAST_STREET.matcher(trimmed).matches() ? trimmed : null;
    }

    // Same rule as the PHP splitRecords(): once a record has its name and street, a line that is neither
    // street nor phone starts the next record if a street follows it right away.
    private void processBuffer(List<String> lines, int startLine, String language) {
        int start = 0;
        boolean hasName = false;
        boolean hasStreet = false;
        for (int i = 0; i < lines.size(); i++) {
            String line = lines.get(i);
            boolean isPhone = phoneIn(line, language) != null;
            boolean isStreet = isStreetLine(line, language, hasName);
            if (hasName && hasStreet && !isPhone && !isStreet
                    && i + 1 < lines.size() && isStreetLine(lines.get(i + 1), language, true)) {
                processRecord(lines.subList(start, i), startLine + start, language);
                start = i;
                hasStreet = false;
                continue;
            }
            hasName = hasName || (!isPhone && !isStreet);
            hasStreet = hasStreet || isStreet;
        }
        processRecord(lines.subList(start, lines.size()), startLine + start, language);
    }

    private void processRecord(List<String> lines, int startLine, String language) {
        Map<String, String> data = new HashMap<>();
        data.put("name", null);
        data.put("phone", null);
        data.put("street", null);
        data.put("country", countryCode);

        for (String line : lines) {
            String phone = phoneIn(line, language);
            if (data.get("phone") == null && phone != null) {
                data.put("phone", phone);
            }

            String street = extractStreet(line, language);
            // Same rule as the PHP StreetFallback::numberLast, and only once the name is read:
            // "Restaurante Siglo 21" has the same shape as "Los Aromos 123".
            if (street == null && data.get("name") != null) {
                street = afterNameStreet(line, language);
            }
            if (data.get("street") == null && street != null) {
                data.put("street", street);
            }

            if (data.get("name") == null && street == null) {
                // A phone line can also carry the name ("SMITH, John ........ 555-123-4567").
                String name = phone == null ? line : nameBesidePhone(repairOcrDigits(line), phone);
                if (name != null) {
                    data.put("name", name);
                }
            }
        }

        if (data.get("name") == null || data.get("street") == null) {
            Map<String, Object> error = new HashMap<>();
            error.put("line", startLine);
            error.put("reason", "Missing name or street");
            error.put("data", new HashMap<>(data));
            errors.add(error);
            return;
        }

        try {
            Map<String, Object> entry = new HashMap<>();
            entry.put("type", "natural");
            entry.put("person_name", new PersonName(data.get("name"), language).toMap());
            entry.put("location", new GeoLocation(
                data.get("country"),
                data.get("street")
            ).toMap());
            entry.put("phone", data.get("phone"));
            entry.put("record_date", LocalDateTime.now().toString());
            entry.put("language", language);
            entries.add(entry);
        } catch (Exception e) {
            Map<String, Object> error = new HashMap<>();
            error.put("line", startLine);
            error.put("reason", e.getMessage());
            error.put("data", new HashMap<>(data));
            errors.add(error);
        }
    }

    private String extractStreet(String line, String language) {
        Pattern markers = wordsPattern(
            STREET_MARKERS.getOrDefault(language, STREET_MARKERS.get("en")),
            STREET_SUFFIXES.getOrDefault(language, List.of())
        );
        if (markers.matcher(line).find()) {
            return line;
        }

        // A line that is only a phone number is not also a street, even though it starts with digits.
        if (line.matches("[\\d\\s().+-]+")) {
            return null;
        }

        // Same rule as the PHP StreetFallback: the whole line is a house number followed only by words.
        String trimmed = line.trim();
        return NUMBER_FIRST_STREET.matcher(trimmed).matches() ? trimmed : null;
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
