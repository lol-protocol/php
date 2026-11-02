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
        if (!expected.equals(actual)) {
            System.err.println("FAIL: expected <" + expected + "> but was <" + actual + ">");
            System.exit(1);
        }
    }
}
