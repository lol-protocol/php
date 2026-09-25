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
    private List<String> firstNames;
    private List<String> lastNames;

    private static final Map<String, List<String>> LAST_NAME_PARTICLES =
        Map.ofEntries(
            Map.entry("es", List.of("de", "del", "di", "da", "y")),
            Map.entry("en", List.of("von", "van", "de")),
            Map.entry("fr", List.of("de", "du", "le", "la")),
            Map.entry("de", List.of("von", "van")),
            Map.entry("it", List.of("di", "da")),
            Map.entry("pt", List.of("de", "da"))
        );

    public PersonName(String fullName) {
        if (fullName == null || fullName.trim().isEmpty()) {
            throw new IllegalArgumentException("Full name cannot be empty");
        }
        this.fullName = fullName;
        parseName();
    }

    private void parseName() {
        String[] parts = fullName.trim().split("\\s+");

        if (parts.length < 2) {
            firstNames = new ArrayList<>(Arrays.asList(parts));
            lastNames = new ArrayList<>();
            return;
        }

        // Find name particle
        int splitIndex = parts.length / 2;
        Set<String> particles = new HashSet<>();
        LAST_NAME_PARTICLES.values().forEach(particles::addAll);

        for (int i = 1; i < parts.length; i++) {
            if (particles.contains(parts[i].toLowerCase())) {
                splitIndex = i;
                break;
            }
        }

        firstNames = new ArrayList<>(Arrays.asList(parts).subList(0, splitIndex));
        lastNames = new ArrayList<>(Arrays.asList(parts).subList(splitIndex, parts.length));
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
        if (countryCode == null || countryCode.length() != 2) {
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
            Map.entry("es", List.of("calle", "avenida", "av", "plaza", "pasaje")),
            Map.entry("en", List.of("street", "st", "avenue", "ave", "road", "drive")),
            Map.entry("fr", List.of("rue", "avenue", "allée", "place", "boulevard")),
            Map.entry("pt", List.of("rua", "avenida", "av", "praça", "alameda")),
            Map.entry("de", List.of("straße", "allee", "weg", "platz")),
            Map.entry("it", List.of("via", "viale", "corso", "piazza"))
        );

    private static final String PHONE_PATTERN =
        "\\b\\d{3}[-.]?\\d{3}[-.]?\\d{4}\\b|\\b\\d{10}\\b";

    private List<Map<String, Object>> entries;
    private List<Map<String, Object>> errors;
    private String detectedLanguage;

    public MultiLanguagePhoneDirectoryParser() {
        this.entries = new ArrayList<>();
        this.errors = new ArrayList<>();
        this.detectedLanguage = "en";
    }

    public String detectLanguage(String content) {
        String lowerContent = content.toLowerCase();
        Map<String, Integer> scores = new HashMap<>();

        for (String lang : STREET_MARKERS.keySet()) {
            int score = 0;
            for (String marker : STREET_MARKERS.get(lang)) {
                score += countOccurrences(lowerContent, marker);
            }
            scores.put(lang, score);
        }

        return Collections.max(scores.entrySet(), Map.Entry.comparingByValue())
                          .getKey();
    }

    private int countOccurrences(String text, String pattern) {
        int count = 0;
        int index = 0;
        while ((index = text.indexOf(pattern, index)) != -1) {
            count++;
            index += pattern.length();
        }
        return count;
    }

    public List<Map<String, Object>> parseContent(String content, String language) {
        if (language == null) {
            language = detectLanguage(content);
        }
        detectedLanguage = language;
        entries.clear();
        errors.clear();

        String[] lines = content.split("\n");
        List<String> buffer = new ArrayList<>();

        for (int i = 0; i < lines.length; i++) {
            String line = lines[i].trim();

            if (line.isEmpty() || line.matches("^-{2,}|={2,}$")) {
                if (!buffer.isEmpty()) {
                    processBuffer(new ArrayList<>(buffer), i - buffer.size(), language);
                    buffer.clear();
                }
            } else {
                buffer.add(line);
            }
        }

        if (!buffer.isEmpty()) {
            processBuffer(buffer, lines.length - buffer.size(), language);
        }

        return entries;
    }

    private void processBuffer(List<String> lines, int startLine, String language) {
        Map<String, String> data = new HashMap<>();
        data.put("name", null);
        data.put("phone", null);
        data.put("street", null);
        data.put("country", "US");

        for (String line : lines) {
            if (data.get("phone") == null) {
                Pattern p = Pattern.compile(PHONE_PATTERN);
                Matcher m = p.matcher(line);
                if (m.find()) {
                    data.put("phone", m.group());
                }
            }

            if (data.get("street") == null) {
                String street = extractStreet(line, language);
                if (street != null) {
                    data.put("street", street);
                }
            }

            if (data.get("name") == null && extractStreet(line, language) == null) {
                data.put("name", line);
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
            entry.put("person_name", new PersonName(data.get("name")).toMap());
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
        List<String> markers = STREET_MARKERS.getOrDefault(language,
            STREET_MARKERS.get("en"));

        String lowerLine = line.toLowerCase();
        for (String marker : markers) {
            if (lowerLine.contains(marker)) {
                return line;
            }
        }

        Pattern p = Pattern.compile("\\d+\\s+[\\w\\s]+");
        Matcher m = p.matcher(line);
        if (m.find()) {
            return m.group();
        }

        return null;
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
