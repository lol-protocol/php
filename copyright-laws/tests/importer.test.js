// import_database.php sin base de datos: columnas, anchos, --yes y fechas. El código de las pruebas es
// el mismo para los dos módulos: leyes-comunes/importer-tests-core.js.
import { defineImporterTests } from "../../leyes-comunes/importer-tests-core.js";
import { COLUMNS, MASTER_CSV, ROOT, loadMaster } from "../scripts/lib/dataset.js";

defineImporterTests({ root: ROOT, loadMaster, masterCsv: MASTER_CSV, columns: COLUMNS });
