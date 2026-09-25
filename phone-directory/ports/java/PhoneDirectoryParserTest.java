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
