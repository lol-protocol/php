// node scripts/validate-csv.js — strict validation of countries/privacy_laws_master.csv
//   --fail-on-warnings   also exit 1 when there are warnings (rows due for review, pending changes
//                        whose date has passed, sources older than the value they back): the weekly job
import { loadAndValidate } from "./lib/dataset.js";
import { validateCsv } from "../../leyes-comunes/validate-csv-core.js";

process.exit(validateCsv({ loadAndValidate, title: "privacy-laws data" }));
