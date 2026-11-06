import java.util.List;
import java.util.Map;

/**
 * Dependency-free checks for the Java port: `javac *.java && java PhoneDirectoryParserTest`.
 * Exits non-zero on the first failure so CI can run it without JUnit.
 */
public class PhoneDirectoryParserTest {

    public static void main(String[] args) {
        assertEquals("García, Juan José", new PersonName("GARCÍA, Juan José").getFormatted());
        assertEquals("Smith, John Michael", new PersonName("John Michael Smith").getFormatted());
        assertEquals(List.of("Valdez", "Ruiz"), new PersonName("Juan Valdez Ruiz", "es").getLastNames());
        assertEquals("de la Cruz, María", new PersonName("María de la Cruz").getFormatted());

        MultiLanguagePhoneDirectoryParser parser = new MultiLanguagePhoneDirectoryParser();
        List<Map<String, Object>> entries = parser.parseContent("García, Juan José\nCalle Principal 123\n555-1234567", null);
        assertEquals("es", parser.getDetectedLanguage());
        assertEquals(0, parser.getErrors().size());
        assertEquals("Calle Principal 123", location(entries.get(0)).get("street"));

        // "Kristen" contains "st" and "Olivia" contains "via"; neither is a street.
        entries = new MultiLanguagePhoneDirectoryParser().parseContent("Kristen Olivia\n12 Oak Avenue", "en");
        assertEquals("Olivia, Kristen", person(entries.get(0)).get("formatted"));

        entries = new MultiLanguagePhoneDirectoryParser().parseContent("Hans Müller\nHauptstraße 5", null);
        assertEquals("Hauptstraße 5", location(entries.get(0)).get("street"));

        // A street with no street word: the whole line, accents and hyphens included.
        entries = new MultiLanguagePhoneDirectoryParser().parseContent("Hélène Lefèvre\n8 Clos Saint-Éloi", "fr");
        assertEquals("8 Clos Saint-Éloi", location(entries.get(0)).get("street"));

        // A number inside a name ("3 Hermanos") is not a street.
        entries = new MultiLanguagePhoneDirectoryParser().parseContent("Restaurante 3 Hermanos\nCalle Larga 5", "es");
        assertEquals(1, entries.size());
        assertEquals("Calle Larga 5", location(entries.get(0)).get("street"));

        // A street with no street word and the house number last, once the name is read.
        entries = new MultiLanguagePhoneDirectoryParser().parseContent("COSTA, Rui\nTravessa do Carmo 5", "pt");
        assertEquals(1, entries.size());
        assertEquals("Travessa do Carmo 5", location(entries.get(0)).get("street"));

        // A dwelling line is the street once the name is read.
        entries = new MultiLanguagePhoneDirectoryParser().parseContent("TAYLOR, Jim\nApt 4 Building 7\n555-123-4567", "en");
        assertEquals(1, entries.size());
        assertEquals("Apt 4 Building 7", location(entries.get(0)).get("street"));

        // A name ending in a number stays a name.
        entries = new MultiLanguagePhoneDirectoryParser().parseContent("Restaurante Siglo 21\nCalle Larga 5", "es");
        assertEquals(1, entries.size());
        assertEquals("Calle Larga 5", location(entries.get(0)).get("street"));

        // Records with no blank line between them; a city line does not start a record.
        entries = new MultiLanguagePhoneDirectoryParser().parseContent("HALL, Ruth\n3 Main Street\n555-123-4567\nLEE, Ken\n8 Oak Lane\n555-987-6543", "en");
        assertEquals(2, entries.size());
        assertEquals("8 Oak Lane", location(entries.get(1)).get("street"));
        entries = new MultiLanguagePhoneDirectoryParser().parseContent("SMITH, John\n12 Oak Street\nSpringfield\n555-123-4567", "en");
        assertEquals(1, entries.size());

        // "Via" counts as Italian only in the "Via Roma 10" shape.
        assertEquals("it", new MultiLanguagePhoneDirectoryParser().detectLanguage("ROSSI, Luigi\nVia Roma 10"));
        assertEquals("en", new MultiLanguagePhoneDirectoryParser().detectLanguage("SMITH, John\n12 Via Lane"));

        entries = new MultiLanguagePhoneDirectoryParser().parseContent("Ann Jones\n5 Pine Rd\n- - -\nBob Lee\n7 Oak St", null);
        assertEquals(2, entries.size());

        parser = new MultiLanguagePhoneDirectoryParser();
        parser.parseContent("\n\nNo Street Here\n", null);
        assertEquals(3, parser.getErrors().get(0).get("line"));

        entries = new MultiLanguagePhoneDirectoryParser("mx").parseContent("Juan Pérez\nCalle Hidalgo 4", null);
        assertEquals("MX", location(entries.get(0)).get("countryCode"));

        // Each language reads its own countries' phone formats, and only its own.
        assertEquals("01 42 68 53 00", MultiLanguagePhoneDirectoryParser.phoneIn("Tel 01 42 68 53 00", "fr"));
        assertEquals("+33 1 42 68 53 00", MultiLanguagePhoneDirectoryParser.phoneIn("+33 1 42 68 53 00", "fr"));
        assertEquals("(089) 654321", MultiLanguagePhoneDirectoryParser.phoneIn("(089) 654321", "de"));
        assertEquals("0711/12345", MultiLanguagePhoneDirectoryParser.phoneIn("0711/12345", "de"));
        assertEquals("06 1234567", MultiLanguagePhoneDirectoryParser.phoneIn("06 1234567", "it"));
        assertEquals("21 342 0000", MultiLanguagePhoneDirectoryParser.phoneIn("21 342 0000", "pt"));
        assertEquals("91 234 56 78", MultiLanguagePhoneDirectoryParser.phoneIn("91 234 56 78", "es"));
        assertEquals("2-345-678", MultiLanguagePhoneDirectoryParser.phoneIn("2-345-678", "es"));
        assertEquals(null, MultiLanguagePhoneDirectoryParser.phoneIn("Tel 01 42 68 53 00", "en"));
        assertEquals("555-123-4567", MultiLanguagePhoneDirectoryParser.phoneIn("Calle Mayor 12 555-123-4567", "es"));
        assertEquals("030 123456", MultiLanguagePhoneDirectoryParser.phoneIn("Hauptstraße 5 030 123456", "de"));
        assertEquals("BUtterfield 8-4521", MultiLanguagePhoneDirectoryParser.phoneIn("BUtterfield 8-4521", "en"));

        // OCR misreadings inside a phone are repaired; words and exchange names are left alone.
        assertEquals("555-123-4567", MultiLanguagePhoneDirectoryParser.repairOcrDigits("555-l23-4567"));
        assertEquals("555-123-4567", MultiLanguagePhoneDirectoryParser.phoneIn("555-l23-4567", "en"));
        assertEquals("Oil BUtterfield 8-4521", MultiLanguagePhoneDirectoryParser.repairOcrDigits("Oil BUtterfield 8-4521"));

        // The phone can share the line with the name.
        entries = new MultiLanguagePhoneDirectoryParser().parseContent("SMITH, John ........ 555-123-4567\n12 Main Street", "en");
        assertEquals("555-123-4567", entries.get(0).get("phone"));
        assertEquals("Smith, John", person(entries.get(0)).get("formatted"));
        assertEquals(null, MultiLanguagePhoneDirectoryParser.nameBesidePhone("555-123-4567 ext. 12", "555-123-4567"));

        System.out.println("All Java port checks passed");
    }

    @SuppressWarnings("unchecked")
    private static Map<String, Object> person(Map<String, Object> entry) {
        return (Map<String, Object>) entry.get("person_name");
    }

    @SuppressWarnings("unchecked")
    private static Map<String, Object> location(Map<String, Object> entry) {
        return (Map<String, Object>) entry.get("location");
    }

    private static void assertEquals(Object expected, Object actual) {
        if (!java.util.Objects.equals(expected, actual)) {
            System.err.println("FAIL: expected <" + expected + "> but was <" + actual + ">");
            System.exit(1);
        }
    }
}
