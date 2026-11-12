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

    /** How many leading words of a comma-less name are the surname in this language. */
    static int surnameCount(String language) {
        return language == null ? 1 : LANGUAGE_SURNAME_COUNT.getOrDefault(language, 1);
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
 * Multi-language phone directory parser.
 */
class MultiLanguagePhoneDirectoryParser {

    private static final Map<String, List<String>> STREET_MARKERS =
        Map.ofEntries(
            Map.entry("es", List.of("calle", "c/", "avenida", "av", "plaza", "pasaje", "camino", "ruta", "carrera")),
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
                continue;
            }

            // Most historical directories print one full record per line; try that shape first.
            String[] single = splitSingleLine(line, language);
            if (single != null) {
                if (!buffer.isEmpty()) {
                    processBuffer(new ArrayList<>(buffer), bufferStart, language);
                    buffer.clear();
                }
                finalizeEntry(single[0], single[1], single[2], businessTypeOfName(single[0], language), i + 1, language);
                continue;
            }

            if (buffer.isEmpty()) {
                bufferStart = i + 1;
            }
            buffer.add(line);
        }

        if (!buffer.isEmpty()) {
            processBuffer(buffer, bufferStart, language);
        }

        return entries;
    }

    // ---- Street rules: same as the PHP StreetRules and StreetFallback ----

    private static final int UNICODE_CI = Pattern.CASE_INSENSITIVE | Pattern.UNICODE_CASE | Pattern.UNICODE_CHARACTER_CLASS;
    private static final Pattern LETTER = Pattern.compile("\\p{L}");
    private static final Pattern DIGIT = Pattern.compile("\\d");

    // English street words that also open a name ("Dr. SMITH, John", "St. Mary Lodge"): they only make a
    // street when the line has a digit or they are not its first word.
    private static final Map<String, List<String>> NAME_ABBREVIATIONS = Map.of("en", List.of("dr", "st"));

    private static final Map<String, Pattern> STREET_PATTERNS = new HashMap<>();

    // Not \b: "c/" ends in a slash, and \b only sees a word edge after a letter or digit.
    private static String bounded(List<String> words) {
        List<String> quoted = new ArrayList<>();
        words.forEach(w -> quoted.add(Pattern.quote(w)));
        return "(?<![\\p{L}\\d])(?:" + String.join("|", quoted) + ")(?![\\p{L}\\d])";
    }

    /** The street words of the language; the abbreviations of NAME_ABBREVIATIONS are not in it. */
    private static synchronized Pattern streetPattern(String language) {
        return STREET_PATTERNS.computeIfAbsent(language, lang -> {
            List<String> abbreviations = NAME_ABBREVIATIONS.getOrDefault(lang, List.of());
            List<String> markers = new ArrayList<>(STREET_MARKERS.getOrDefault(lang, STREET_MARKERS.get("en")));
            markers.removeAll(abbreviations);
            StringBuilder regex = new StringBuilder(bounded(markers));
            for (String suffix : STREET_SUFFIXES.getOrDefault(lang, List.of())) {
                regex.append("|(?:").append(Pattern.quote(suffix)).append(")\\b");
            }
            return Pattern.compile(regex.toString(), UNICODE_CI);
        });
    }

    private boolean hasStreetWord(String line, String language) {
        if (streetPattern(language).matcher(line).find()) {
            return true;
        }
        List<String> abbreviations = NAME_ABBREVIATIONS.get(language);
        if (abbreviations == null) {
            return false;
        }
        Matcher m = Pattern.compile(bounded(abbreviations), UNICODE_CI).matcher(line);
        boolean hasDigit = DIGIT.matcher(line).find();
        while (m.find()) {
            if (hasDigit || !line.substring(0, m.start()).trim().isEmpty()) {
                return true;
            }
        }
        return false;
    }

    private static boolean isOnlyAPhone(String text, String language) {
        String trimmed = text.trim();
        Matcher m = phoneRegex(language).matcher(trimmed);
        return !trimmed.isEmpty() && m.find() && m.group().equals(trimmed);
    }

    /** A street by its street word, or a house number followed only by words (StreetRules::strong). */
    private String extractStreet(String line, String language) {
        if (hasStreetWord(line, language)) {
            return line;
        }
        // A line that is only a phone number is not also a street, even though it starts with digits.
        if (isOnlyAPhone(line, language)) {
            return null;
        }
        String trimmed = line.trim();
        return NUMBER_FIRST_STREET.matcher(trimmed).matches() ? trimmed : null;
    }

    // Same as the PHP StreetRules::afterName: a dwelling line in English, a number-last street without a street word elsewhere.
    private String afterNameStreet(String line, String language) {
        String trimmed = line.trim();
        if ("en".equals(language)) {
            return DWELLING.matcher(trimmed).matches() ? trimmed : null;
        }
        return NUMBER_LAST_LANGUAGES.contains(language) && NUMBER_LAST_STREET.matcher(trimmed).matches() ? trimmed : null;
    }

    private static final Pattern WORD_START = Pattern.compile("[\\p{L}\\p{M}'’.\\-]*$");

    /**
     * Where the street starts before a number-last house number ("GARCÍA Juan Calle Mayor" gives the offset of
     * "Calle"): the first street word after the first two words, which belong to the name. -1 if none.
     */
    private static int streetWordOffset(String text, String language) {
        Matcher m = streetPattern(language).matcher(text);
        while (m.find()) {
            Matcher start = WORD_START.matcher(text.substring(0, m.start()));
            int offset = m.start() - (start.find() ? start.group().length() : 0);
            String before = text.substring(0, offset).trim();
            if (!before.isEmpty() && before.split("\\s+").length >= 2) {
                return offset;
            }
        }
        return -1;
    }

    // ---- Businesses: same as the PHP ENTITY_TYPE_MARKERS and businessTypeOfName() ----

    private static final Map<String, List<String>> ENTITY_TYPE_MARKERS = Map.of(
        "es", List.of("spa", "srl", "sa", "ltda", "inc", "comercial", "empresa", "negocio", "tienda", "restaurante",
            "hotel", "banco", "farmacia", "hospital", "hnos", "hermanos", "hijos", "sucesores", "taller", "ferretería",
            "clínica", "panadería", "carnicería", "librería", "zapatería"),
        "en", List.of("corp", "inc", "ltd", "llc", "company", "store", "shop", "restaurant", "hotel", "bank",
            "pharmacy", "hospital", "sons", "bros", "associates", "enterprises", "industries", "services", "plumbing",
            "garage", "bakery", "hardware", "clinic", "pub", "motel"),
        "fr", List.of("sarl", "sas", "eirl", "eurl", "magasin", "restaurant", "hôtel", "banque", "pharmacie",
            "hôpital", "frères", "boulangerie", "boucherie", "garage", "clinique", "quincaillerie", "cabinet"),
        "pt", List.of("ltda", "lda", "eireli", "empresa", "comercial", "loja", "padaria", "restaurante", "hotel",
            "banco", "farmácia", "hospital", "irmãos", "filhos", "oficina", "clínica", "mercearia", "ferragens",
            "sapataria"),
        "de", List.of("gmbh", "ag", "kg", "ohg", "apotheke", "bäckerei", "gasthaus", "restaurant", "hotel", "bank",
            "krankenhaus", "gebrüder", "söhne", "werkstatt", "metzgerei", "tischlerei", "klinik"),
        "it", List.of("srl", "spa", "snc", "sas", "negozio", "panificio", "trattoria", "ristorante", "albergo",
            "hotel", "banca", "farmacia", "ospedale", "fratelli", "figli", "officina", "macelleria", "ferramenta",
            "pasticceria", "pizzeria", "clinica")
    );

    private static final Map<String, Pattern> ENTITY_PATTERNS = new HashMap<>();
    private static final Pattern GIVEN_NAME_PART = Pattern.compile("\\p{L}[\\p{L}'. -]*");

    private static synchronized String extractBusinessType(String line, String language) {
        if (!ENTITY_TYPE_MARKERS.containsKey(language)) {
            return null;
        }
        Pattern p = ENTITY_PATTERNS.computeIfAbsent(language, lang -> {
            List<String> quoted = new ArrayList<>();
            ENTITY_TYPE_MARKERS.get(lang).forEach(w -> quoted.add(Pattern.quote(w)));
            return Pattern.compile("\\b(" + String.join("|", quoted) + ")\\b", UNICODE_CI);
        });
        Matcher m = p.matcher(line);
        return m.find() ? m.group(1).toLowerCase() : null;
    }

    /**
     * The business word in a name, unless the name is "<that word>, <given name>": "BANK, Henry" is a person
     * whose surname happens to be a business word.
     */
    private static String businessTypeOfName(String name, String language) {
        String type = extractBusinessType(name, language);
        if (type == null) {
            return null;
        }
        String[] parts = name.split(",", -1);
        boolean isSurnameAndGivenName = parts.length == 2
            && parts[0].trim().toLowerCase().equals(type)
            && GIVEN_NAME_PART.matcher(parts[1].trim()).matches()
            && extractBusinessType(parts[1], language) == null;
        return isSurnameAndGivenName ? null : type;
    }

    // ---- One record per line: same as the PHP SingleLineEntrySplitter ----

    private static final Pattern FIELD_DELIMITER =
        Pattern.compile("\\s*,\\s*|(?:\\.\\s?){2,}|\\t+|[ ]{2,}|[-\\u2013\\u2014]{2,}");
    private static final String LEADERS = "[ \\t\\n\\r\\x00\\x0B.,\\-\\u2013\\u2014]+";

    private static String trimLeaders(String text) {
        return text.replaceAll("^" + LEADERS + "|" + LEADERS + "$", "");
    }

    /** {name, street, phone} when the line is a whole record, else null. */
    private String[] splitSingleLine(String line, String language) {
        // A phone the scan misread ("555-l23-4567") would otherwise pass for a street starting with a digit.
        line = repairOcrDigits(line);
        List<String> fields = new ArrayList<>();
        for (String field : FIELD_DELIMITER.split(line)) {
            if (!field.isEmpty()) {
                fields.add(field.trim());
            }
        }

        if (fields.size() >= 2) {
            String[] result = splitByFields(fields, language);
            if (result != null) {
                return result;
            }
        }

        String[] result = splitByDigitBoundary(line, language);
        if (result == null) {
            return null;
        }
        // "SMITH John" -> "SMITH, John": directories are surname-first even without a comma.
        String[] words = result[0].trim().split("\\s+");
        int surnameCount = PersonName.surnameCount(language);
        if (!result[0].contains(",") && words.length > surnameCount) {
            result[0] = String.join(" ", Arrays.copyOfRange(words, 0, surnameCount))
                + ", " + String.join(" ", Arrays.copyOfRange(words, surnameCount, words.length));
        }
        return result;
    }

    private String[] splitByFields(List<String> fields, String language) {
        String phone = null;
        for (String field : fields) {
            if (isOnlyAPhone(field, language)) {
                phone = field;
                break;
            }
        }

        int streetIndex = -1;
        for (int i = 0; i < fields.size() && streetIndex < 0; i++) {
            if (!fields.get(i).equals(phone) && hasStreetWord(fields.get(i), language)) {
                streetIndex = i;
            }
        }
        // No field reads as a street by its marker word: the first one that at least starts with a digit.
        for (int i = 0; i < fields.size() && streetIndex < 0; i++) {
            if (!fields.get(i).equals(phone) && !fields.get(i).isEmpty() && Character.isDigit(fields.get(i).charAt(0))) {
                streetIndex = i;
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
        if (!LETTER.matcher(name).find() || street.isEmpty()) {
            return null;
        }
        return new String[] {name, street, phone};
    }

    private String[] splitByDigitBoundary(String line, String language) {
        String rest = line;
        String phone = null;
        Matcher m = phoneRegex(language).matcher(line);
        if (m.find()) {
            phone = m.group();
            // A line that opens with the phone ("555-123-4567 ext. 12") is the end of a record, not a whole one.
            if (!LETTER.matcher(line.substring(0, m.start())).find()) {
                return null;
            }
            rest = line.substring(0, m.start()) + line.substring(m.end());
        }

        Matcher digit = DIGIT.matcher(rest);
        if (!digit.find()) {
            return null;
        }

        String name = trimLeaders(rest.substring(0, digit.start()));
        String street = trimLeaders(rest.substring(digit.start()));

        if (NUMBER_LAST_LANGUAGES.contains(language) && !name.isEmpty()) {
            int cut = streetWordOffset(name, language);
            if (cut > 0 && cut < name.length()) {
                street = name.substring(cut) + " " + street;
                name = trimLeaders(name.substring(0, cut));
            }
        }

        if (name.isEmpty() || street.isEmpty()) {
            return null;
        }
        // With no phone and no street word, a bare "word + number" is too ambiguous to split out.
        if (phone == null && !hasStreetWord(street, language)) {
            return null;
        }
        return new String[] {name, street, phone};
    }

    // ---- Blocks of lines: same as the PHP BlockReader and LineFacts ----

    /** What one line of a block says, worked out once. */
    private final class LineFacts {
        final String line;
        final String phone;
        final String strongStreet;
        final String afterNameStreet;
        final String nameBesidePhone;

        LineFacts(String line, String language) {
            this.line = line;
            this.phone = phoneIn(line, language);
            this.strongStreet = extractStreet(line, language);
            this.afterNameStreet = MultiLanguagePhoneDirectoryParser.this.afterNameStreet(line, language);
            this.nameBesidePhone = phone == null ? null : MultiLanguagePhoneDirectoryParser.nameBesidePhone(repairOcrDigits(line), phone);
        }

        String street(boolean afterName) {
            return strongStreet != null ? strongStreet : (afterName ? afterNameStreet : null);
        }

        String name(boolean afterName) {
            if (street(afterName) != null) {
                return null;
            }
            return phone == null ? line : nameBesidePhone;
        }
    }

    private static final Pattern ENDS_WITH_HYPHENATED_LETTER = Pattern.compile("\\p{L}-$");
    private static final Pattern STARTS_WITH_LETTER = Pattern.compile("^\\p{L}");

    /**
     * Reads a block into records, looking at each line once.
     * - A word the scan broke with a hyphen ("WASHING- / TON, George") is put back together.
     * - Once a record has its name and street, a line that gives a name (a line of text, or a name beside a
     *   phone) starts the next record if a street follows it right away.
     * - A street without a street word gives way to a later one with it.
     * - Any other line of text after the name is a note, read for a business word.
     */
    private void processBuffer(List<String> lines, int startLine, String language) {
        List<String> joined = new ArrayList<>();
        List<Integer> origin = new ArrayList<>();
        for (int i = 0; i < lines.size(); i++) {
            String line = lines.get(i);
            int last = joined.size() - 1;
            if (last >= 0 && ENDS_WITH_HYPHENATED_LETTER.matcher(joined.get(last)).find()
                    && STARTS_WITH_LETTER.matcher(line).find()) {
                joined.set(last, joined.get(last).substring(0, joined.get(last).length() - 1) + line);
                continue;
            }
            joined.add(line);
            origin.add(i);
        }

        List<LineFacts> facts = new ArrayList<>();
        for (String line : joined) {
            facts.add(new LineFacts(line, language));
        }

        int offset = 0;
        String name = null;
        String phone = null;
        String street = null;
        String noteType = null;
        boolean strongStreet = false;

        for (int i = 0; i < facts.size(); i++) {
            LineFacts fact = facts.get(i);
            boolean hasName = name != null;

            if (hasName && street != null && fact.name(true) != null
                    && i + 1 < facts.size() && facts.get(i + 1).street(true) != null) {
                finalizeBlockRecord(name, street, phone, noteType, startLine + offset, language);
                offset = origin.get(i);
                name = null;
                phone = null;
                street = null;
                noteType = null;
                strongStreet = false;
                hasName = false;
            }

            if (phone == null && fact.phone != null) {
                phone = fact.phone;
            }

            String lineStreet = fact.street(hasName);
            if (lineStreet != null && (street == null || (!strongStreet && fact.strongStreet != null))) {
                street = lineStreet;
                strongStreet = fact.strongStreet != null;
            }

            String lineName = fact.name(hasName);
            if (!hasName && lineName != null) {
                name = lineName;
            } else if (hasName && fact.phone == null && lineStreet == null && noteType == null) {
                noteType = extractBusinessType(fact.line, language);
            }
        }

        finalizeBlockRecord(name, street, phone, noteType, startLine + offset, language);
    }

    // Only the name and the notes can say it is a business, never the street.
    private void finalizeBlockRecord(String name, String street, String phone, String noteType, int startLine, String language) {
        String type = name != null ? businessTypeOfName(name, language) : null;
        finalizeEntry(name, street, phone, type != null ? type : noteType, startLine, language);
    }

    /** Stores the record as a person or as a business, or as an error when it has no name or street. */
    private void finalizeEntry(String name, String street, String phone, String businessType, int startLine, String language) {
        Map<String, String> data = new HashMap<>();
        data.put("name", name);
        data.put("phone", phone);
        data.put("street", street);
        data.put("country", countryCode);

        if (name == null || name.isEmpty() || street == null || street.isEmpty()) {
            Map<String, Object> error = new HashMap<>();
            error.put("line", startLine);
            error.put("reason", "Missing name or street");
            error.put("data", data);
            errors.add(error);
            return;
        }

        try {
            Map<String, Object> entry = new HashMap<>();
            if (businessType != null) {
                entry.put("type", "juridical");
                entry.put("business_name", name);
                entry.put("business_type", businessType);
            } else {
                entry.put("type", "natural");
                entry.put("person_name", new PersonName(name, language).toMap());
            }
            entry.put("location", new GeoLocation(countryCode, street).toMap());
            entry.put("phone", phone);
            entry.put("record_date", LocalDateTime.now().toString());
            entry.put("language", language);
            entries.add(entry);
        } catch (Exception e) {
            Map<String, Object> error = new HashMap<>();
            error.put("line", startLine);
            error.put("reason", e.getMessage());
            error.put("data", data);
            errors.add(error);
        }
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
            // A business has no person_name: it carries business_name and business_type.
            Map<String, Object> person = (Map<String, Object>) entry.get("person_name");
            System.out.println("\nName: " + (person != null ? person.get("formatted") : entry.get("business_name")));
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
