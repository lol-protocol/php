// UI translations. Languages are handled with three-letter ISO 639-2 codes
// (eng, spa, fra, deu, por); <html lang> receives the BCP 47 equivalent.
// Law data (names, rights, exceptions…) is shown as published and is not translated.

export const LANGUAGES = {
  eng: "English",
  spa: "Español",
  fra: "Français",
  deu: "Deutsch",
  por: "Português",
};
export const DEFAULT_LANGUAGE = "eng";

const BCP47 = { eng: "en", spa: "es", fra: "fr", deu: "de", por: "pt" };
const BY_ISO2 = Object.fromEntries(Object.entries(BCP47).map(([iso3, iso2]) => [iso2, iso3]));
const STORAGE_KEY = "preferredLanguage";

export const TRANSLATIONS = {
  eng: {
    "page.title": "Copyright Laws by Jurisdiction",
    "page.heading": "Copyright Laws by Jurisdiction",
    "page.subtitle": "Find copyright legislation for digital libraries, authors, and PDF documentation projects",
    "lang.label": "Language",

    "search.label": "Search",
    "search.placeholder": "Jurisdiction, law, authority or keyword…",
    "search.hint": "Use \"quotes\" for an exact phrase. Short codes such as US or BR match whole words only.",
    "search.scope": "Search in",
    "scope.all": "All fields",
    "scope.name": "Jurisdiction & law name",
    "scope.rights": "Rights & exceptions",
    "scope.authority": "Enforcement body",

    "btn.search": "Search",
    "btn.clear": "Clear",
    "btn.compare": "Compare selected",
    "btn.compareCount": "Compare selected ({count})",
    "btn.export": "Export CSV",
    "btn.close": "Close",

    "filters.advanced": "Advanced filters",
    "filters.active.one": "{count} active",
    "filters.active.other": "{count} active",
    "filters.region": "Region",
    "filters.treaty": "Treaties",
    "filters.treatyHint": "Party to all selected",
    "filters.term": "Protection term",
    "filters.ptype": "Protection type",
    "filters.clearAll": "Clear all filters",
    "filters.activeLabel": "Active filters",
    "chip.country": "Jurisdiction: {value}",
    "chip.treaty": "Treaty: {value}",
    "chip.term": "Term: {value}",
    "chip.ptype": "Type: {value}",
    "chip.remove": "Remove filter {value}",

    "region.europe": "Europe",
    "region.americas": "Americas",
    "region.asia_pacific": "Asia-Pacific",
    "region.middle_east_africa": "Middle East & Africa",

    "treaty.Berne": "Berne Convention",
    "treaty.TRIPS": "TRIPS Agreement",
    "treaty.WCT": "WIPO Copyright Treaty (WCT)",
    "treaty.WPPT": "WIPO Performances and Phonograms Treaty (WPPT)",

    "term.life": "Life + {years} years",
    "term.unspecified": "Other",

    "results.title": "Copyright laws",
    "results.summary": "{shown} of {total} laws · {countries}",
    "results.none": "No laws match these filters.",
    "results.noneHint": "Remove a filter or search for fewer words.",

    "table.caption": "Copyright laws matching the current filters",
    "table.select": "Select",
    "table.selectAll": "Select all results",
    "table.country": "Jurisdiction",
    "table.law": "Law",
    "table.term": "Term",
    "table.author": "Author rights",
    "table.moral": "Moral rights",
    "table.treaties": "Treaties",
    "table.reference": "Reference",
    "table.open": "Open official reference",
    "table.onlyCountry": "Show only {name}",

    "compare.title": "Copyright protection comparison",
    "compare.criterion": "Criterion",
    "compare.protection_type": "Protection type",
    "compare.term_of_protection": "Term of protection",
    "compare.author_rights": "Author rights",
    "compare.moral_rights": "Moral rights",
    "compare.orphan_works": "Orphan works",
    "compare.digital_protection": "Digital protection",
    "compare.fair_use_exceptions": "Fair use & exceptions",
    "compare.registration_required": "Registration required",
    "compare.treaties_signatory": "Treaties",
    "compare.needTwo": "Select laws from at least 2 different jurisdictions to compare.",

    "chart.title": "Laws by term of protection",
    "chart.subtitle": "{laws} · years after the author's death",
    "chart.showTable": "View as table",
    "chart.showChart": "View as chart",
    "chart.colTerm": "Term",
    "chart.colLaws": "Laws",
    "chart.colDetails": "Details",
    "chart.more": "+{count} more",

    "unit.law.one": "{count} law",
    "unit.law.other": "{count} laws",
    "unit.jurisdiction.one": "{count} jurisdiction",
    "unit.jurisdiction.other": "{count} jurisdictions",

    "card.region": "Region",
    "card.laws": "Laws",
    "card.treaties": "Treaties",
    "card.folder": "Data folder",
    "card.json": "Download JSON",
    "card.csv": "Download CSV",
    "card.updated": "Data updated",

    "footer.data": "Data updated {date} · version {version} · {laws} laws · {countries}",
    "footer.note": "For digital libraries, text repositories and PDF documentation projects. Terms are summaries: always check the official source before relying on an entry.",
    "status.error": "Could not load the copyright laws database. Please reload the page.",
  },

  spa: {
    "page.title": "Leyes de derechos de autor por jurisdicción",
    "page.heading": "Leyes de derechos de autor por jurisdicción",
    "page.subtitle": "Encuentra legislación sobre derechos de autor para bibliotecas digitales, autores y proyectos de documentación PDF",
    "lang.label": "Idioma",

    "search.label": "Buscar",
    "search.placeholder": "Jurisdicción, ley, autoridad o palabra clave…",
    "search.hint": "Usa \"comillas\" para una frase exacta. Los códigos cortos, como US o BR, solo coinciden con palabras completas.",
    "search.scope": "Buscar en",
    "scope.all": "Todos los campos",
    "scope.name": "Jurisdicción y nombre de la ley",
    "scope.rights": "Derechos y excepciones",
    "scope.authority": "Organismo competente",

    "btn.search": "Buscar",
    "btn.clear": "Limpiar",
    "btn.compare": "Comparar seleccionadas",
    "btn.compareCount": "Comparar seleccionadas ({count})",
    "btn.export": "Exportar CSV",
    "btn.close": "Cerrar",

    "filters.advanced": "Filtros avanzados",
    "filters.active.one": "{count} activo",
    "filters.active.other": "{count} activos",
    "filters.region": "Región",
    "filters.treaty": "Tratados",
    "filters.treatyHint": "Parte en todos los seleccionados",
    "filters.term": "Plazo de protección",
    "filters.ptype": "Tipo de protección",
    "filters.clearAll": "Quitar todos los filtros",
    "filters.activeLabel": "Filtros activos",
    "chip.country": "Jurisdicción: {value}",
    "chip.treaty": "Tratado: {value}",
    "chip.term": "Plazo: {value}",
    "chip.ptype": "Tipo: {value}",
    "chip.remove": "Quitar filtro {value}",

    "region.europe": "Europa",
    "region.americas": "América",
    "region.asia_pacific": "Asia-Pacífico",
    "region.middle_east_africa": "Oriente Medio y África",

    "treaty.Berne": "Convenio de Berna",
    "treaty.TRIPS": "Acuerdo sobre los ADPIC (TRIPS)",
    "treaty.WCT": "Tratado de la OMPI sobre Derecho de Autor (WCT)",
    "treaty.WPPT": "Tratado de la OMPI sobre Interpretación o Ejecución y Fonogramas (WPPT)",

    "term.life": "Vida del autor + {years} años",
    "term.unspecified": "Otro",

    "results.title": "Leyes de derechos de autor",
    "results.summary": "{shown} de {total} leyes · {countries}",
    "results.none": "Ninguna ley coincide con estos filtros.",
    "results.noneHint": "Quita algún filtro o busca con menos palabras.",

    "table.caption": "Leyes de derechos de autor que cumplen los filtros actuales",
    "table.select": "Seleccionar",
    "table.selectAll": "Seleccionar todos los resultados",
    "table.country": "Jurisdicción",
    "table.law": "Ley",
    "table.term": "Plazo",
    "table.author": "Derechos del autor",
    "table.moral": "Derechos morales",
    "table.treaties": "Tratados",
    "table.reference": "Referencia",
    "table.open": "Abrir la referencia oficial",
    "table.onlyCountry": "Mostrar solo {name}",

    "compare.title": "Comparación de la protección de derechos de autor",
    "compare.criterion": "Criterio",
    "compare.protection_type": "Tipo de protección",
    "compare.term_of_protection": "Plazo de protección",
    "compare.author_rights": "Derechos del autor",
    "compare.moral_rights": "Derechos morales",
    "compare.orphan_works": "Obras huérfanas",
    "compare.digital_protection": "Protección digital",
    "compare.fair_use_exceptions": "Uso justo y excepciones",
    "compare.registration_required": "Registro obligatorio",
    "compare.treaties_signatory": "Tratados",
    "compare.needTwo": "Selecciona leyes de al menos 2 jurisdicciones distintas para compararlas.",

    "chart.title": "Leyes por plazo de protección",
    "chart.subtitle": "{laws} · años tras la muerte del autor",
    "chart.showTable": "Ver como tabla",
    "chart.showChart": "Ver como gráfico",
    "chart.colTerm": "Plazo",
    "chart.colLaws": "Leyes",
    "chart.colDetails": "Detalle",
    "chart.more": "+{count} más",

    "unit.law.one": "{count} ley",
    "unit.law.other": "{count} leyes",
    "unit.jurisdiction.one": "{count} jurisdicción",
    "unit.jurisdiction.other": "{count} jurisdicciones",

    "card.region": "Región",
    "card.laws": "Leyes",
    "card.treaties": "Tratados",
    "card.folder": "Carpeta de datos",
    "card.json": "Descargar JSON",
    "card.csv": "Descargar CSV",
    "card.updated": "Datos actualizados",

    "footer.data": "Datos actualizados el {date} · versión {version} · {laws} leyes · {countries}",
    "footer.note": "Para bibliotecas digitales, repositorios de textos y proyectos de documentación PDF. Los plazos son resúmenes: comprueba siempre la fuente oficial antes de basarte en un registro.",
    "status.error": "No se pudo cargar la base de datos de leyes de derechos de autor. Recarga la página.",
  },

  fra: {
    "page.title": "Lois sur le droit d'auteur par juridiction",
    "page.heading": "Lois sur le droit d'auteur par juridiction",
    "page.subtitle": "Trouvez la législation sur le droit d'auteur pour les bibliothèques numériques, les auteurs et les projets de documentation PDF",
    "lang.label": "Langue",

    "search.label": "Rechercher",
    "search.placeholder": "Juridiction, loi, autorité ou mot-clé…",
    "search.hint": "Utilisez des « guillemets » pour une expression exacte. Les codes courts comme US ou BR ne correspondent qu'à des mots entiers.",
    "search.scope": "Rechercher dans",
    "scope.all": "Tous les champs",
    "scope.name": "Juridiction et nom de la loi",
    "scope.rights": "Droits et exceptions",
    "scope.authority": "Organisme compétent",

    "btn.search": "Rechercher",
    "btn.clear": "Effacer",
    "btn.compare": "Comparer la sélection",
    "btn.compareCount": "Comparer la sélection ({count})",
    "btn.export": "Exporter en CSV",
    "btn.close": "Fermer",

    "filters.advanced": "Filtres avancés",
    "filters.active.one": "{count} actif",
    "filters.active.other": "{count} actifs",
    "filters.region": "Région",
    "filters.treaty": "Traités",
    "filters.treatyHint": "Partie à tous les traités sélectionnés",
    "filters.term": "Durée de protection",
    "filters.ptype": "Type de protection",
    "filters.clearAll": "Effacer tous les filtres",
    "filters.activeLabel": "Filtres actifs",
    "chip.country": "Juridiction : {value}",
    "chip.treaty": "Traité : {value}",
    "chip.term": "Durée : {value}",
    "chip.ptype": "Type : {value}",
    "chip.remove": "Retirer le filtre {value}",

    "region.europe": "Europe",
    "region.americas": "Amériques",
    "region.asia_pacific": "Asie-Pacifique",
    "region.middle_east_africa": "Moyen-Orient et Afrique",

    "treaty.Berne": "Convention de Berne",
    "treaty.TRIPS": "Accord sur les ADPIC (TRIPS)",
    "treaty.WCT": "Traité de l'OMPI sur le droit d'auteur (WCT)",
    "treaty.WPPT": "Traité de l'OMPI sur les interprétations et exécutions et les phonogrammes (WPPT)",

    "term.life": "Vie de l'auteur + {years} ans",
    "term.unspecified": "Autre",

    "results.title": "Lois sur le droit d'auteur",
    "results.summary": "{shown} sur {total} lois · {countries}",
    "results.none": "Aucune loi ne correspond à ces filtres.",
    "results.noneHint": "Retirez un filtre ou cherchez avec moins de mots.",

    "table.caption": "Lois sur le droit d'auteur correspondant aux filtres actuels",
    "table.select": "Sélectionner",
    "table.selectAll": "Sélectionner tous les résultats",
    "table.country": "Juridiction",
    "table.law": "Loi",
    "table.term": "Durée",
    "table.author": "Droits de l'auteur",
    "table.moral": "Droits moraux",
    "table.treaties": "Traités",
    "table.reference": "Référence",
    "table.open": "Ouvrir la référence officielle",
    "table.onlyCountry": "Afficher uniquement {name}",

    "compare.title": "Comparaison de la protection du droit d'auteur",
    "compare.criterion": "Critère",
    "compare.protection_type": "Type de protection",
    "compare.term_of_protection": "Durée de protection",
    "compare.author_rights": "Droits de l'auteur",
    "compare.moral_rights": "Droits moraux",
    "compare.orphan_works": "Œuvres orphelines",
    "compare.digital_protection": "Protection numérique",
    "compare.fair_use_exceptions": "Usage équitable et exceptions",
    "compare.registration_required": "Enregistrement obligatoire",
    "compare.treaties_signatory": "Traités",
    "compare.needTwo": "Sélectionnez des lois d'au moins 2 juridictions différentes pour les comparer.",

    "chart.title": "Lois par durée de protection",
    "chart.subtitle": "{laws} · années après la mort de l'auteur",
    "chart.showTable": "Voir sous forme de tableau",
    "chart.showChart": "Voir sous forme de graphique",
    "chart.colTerm": "Durée",
    "chart.colLaws": "Lois",
    "chart.colDetails": "Détails",
    "chart.more": "+{count} autres",

    "unit.law.one": "{count} loi",
    "unit.law.other": "{count} lois",
    "unit.jurisdiction.one": "{count} juridiction",
    "unit.jurisdiction.other": "{count} juridictions",

    "card.region": "Région",
    "card.laws": "Lois",
    "card.treaties": "Traités",
    "card.folder": "Dossier de données",
    "card.json": "Télécharger le JSON",
    "card.csv": "Télécharger le CSV",
    "card.updated": "Données mises à jour",

    "footer.data": "Données mises à jour le {date} · version {version} · {laws} lois · {countries}",
    "footer.note": "Pour les bibliothèques numériques, les dépôts de textes et les projets de documentation PDF. Les durées sont des résumés : vérifiez toujours la source officielle avant de vous appuyer sur une entrée.",
    "status.error": "Impossible de charger la base de données des lois sur le droit d'auteur. Rechargez la page.",
  },

  deu: {
    "page.title": "Urheberrechtsgesetze nach Rechtsordnung",
    "page.heading": "Urheberrechtsgesetze nach Rechtsordnung",
    "page.subtitle": "Urheberrechtsgesetze für digitale Bibliotheken, Autorinnen und Autoren sowie PDF-Dokumentationsprojekte finden",
    "lang.label": "Sprache",

    "search.label": "Suchen",
    "search.placeholder": "Rechtsordnung, Gesetz, Behörde oder Stichwort…",
    "search.hint": "Mit „Anführungszeichen“ nach einer exakten Wendung suchen. Kurze Kürzel wie US oder BR treffen nur ganze Wörter.",
    "search.scope": "Suchen in",
    "scope.all": "Alle Felder",
    "scope.name": "Rechtsordnung und Gesetzesname",
    "scope.rights": "Rechte und Schranken",
    "scope.authority": "Zuständige Stelle",

    "btn.search": "Suchen",
    "btn.clear": "Zurücksetzen",
    "btn.compare": "Auswahl vergleichen",
    "btn.compareCount": "Auswahl vergleichen ({count})",
    "btn.export": "CSV exportieren",
    "btn.close": "Schließen",

    "filters.advanced": "Erweiterte Filter",
    "filters.active.one": "{count} aktiv",
    "filters.active.other": "{count} aktiv",
    "filters.region": "Region",
    "filters.treaty": "Abkommen",
    "filters.treatyHint": "Vertragspartei aller ausgewählten",
    "filters.term": "Schutzdauer",
    "filters.ptype": "Schutzart",
    "filters.clearAll": "Alle Filter zurücksetzen",
    "filters.activeLabel": "Aktive Filter",
    "chip.country": "Rechtsordnung: {value}",
    "chip.treaty": "Abkommen: {value}",
    "chip.term": "Schutzdauer: {value}",
    "chip.ptype": "Schutzart: {value}",
    "chip.remove": "Filter {value} entfernen",

    "region.europe": "Europa",
    "region.americas": "Amerika",
    "region.asia_pacific": "Asien-Pazifik",
    "region.middle_east_africa": "Naher Osten und Afrika",

    "treaty.Berne": "Berner Übereinkunft",
    "treaty.TRIPS": "TRIPS-Übereinkommen",
    "treaty.WCT": "WIPO-Urheberrechtsvertrag (WCT)",
    "treaty.WPPT": "WIPO-Vertrag über Darbietungen und Tonträger (WPPT)",

    "term.life": "Lebenszeit des Urhebers + {years} Jahre",
    "term.unspecified": "Sonstige",

    "results.title": "Urheberrechtsgesetze",
    "results.summary": "{shown} von {total} Gesetzen · {countries}",
    "results.none": "Keine Gesetze entsprechen diesen Filtern.",
    "results.noneHint": "Entfernen Sie einen Filter oder suchen Sie mit weniger Wörtern.",

    "table.caption": "Urheberrechtsgesetze, die den aktuellen Filtern entsprechen",
    "table.select": "Auswählen",
    "table.selectAll": "Alle Ergebnisse auswählen",
    "table.country": "Rechtsordnung",
    "table.law": "Gesetz",
    "table.term": "Schutzdauer",
    "table.author": "Urheberrechte",
    "table.moral": "Urheberpersönlichkeitsrechte",
    "table.treaties": "Abkommen",
    "table.reference": "Quelle",
    "table.open": "Offizielle Quelle öffnen",
    "table.onlyCountry": "Nur {name} anzeigen",

    "compare.title": "Vergleich des Urheberrechtsschutzes",
    "compare.criterion": "Kriterium",
    "compare.protection_type": "Schutzart",
    "compare.term_of_protection": "Schutzdauer",
    "compare.author_rights": "Urheberrechte",
    "compare.moral_rights": "Urheberpersönlichkeitsrechte",
    "compare.orphan_works": "Verwaiste Werke",
    "compare.digital_protection": "Digitaler Schutz",
    "compare.fair_use_exceptions": "Fair Use und Schranken",
    "compare.registration_required": "Registrierung erforderlich",
    "compare.treaties_signatory": "Abkommen",
    "compare.needTwo": "Wählen Sie Gesetze aus mindestens 2 verschiedenen Rechtsordnungen zum Vergleichen aus.",

    "chart.title": "Gesetze nach Schutzdauer",
    "chart.subtitle": "{laws} · Jahre nach dem Tod des Urhebers",
    "chart.showTable": "Als Tabelle anzeigen",
    "chart.showChart": "Als Diagramm anzeigen",
    "chart.colTerm": "Schutzdauer",
    "chart.colLaws": "Gesetze",
    "chart.colDetails": "Details",
    "chart.more": "+{count} weitere",

    "unit.law.one": "{count} Gesetz",
    "unit.law.other": "{count} Gesetze",
    "unit.jurisdiction.one": "{count} Rechtsordnung",
    "unit.jurisdiction.other": "{count} Rechtsordnungen",

    "card.region": "Region",
    "card.laws": "Gesetze",
    "card.treaties": "Abkommen",
    "card.folder": "Datenordner",
    "card.json": "JSON herunterladen",
    "card.csv": "CSV herunterladen",
    "card.updated": "Daten aktualisiert",

    "footer.data": "Daten aktualisiert am {date} · Version {version} · {laws} Gesetze · {countries}",
    "footer.note": "Für digitale Bibliotheken, Textarchive und PDF-Dokumentationsprojekte. Die Angaben sind Zusammenfassungen: Prüfen Sie stets die offizielle Quelle, bevor Sie sich auf einen Eintrag verlassen.",
    "status.error": "Die Urheberrechtsgesetz-Datenbank konnte nicht geladen werden. Bitte laden Sie die Seite neu.",
  },

  por: {
    "page.title": "Leis de direitos autorais por jurisdição",
    "page.heading": "Leis de direitos autorais por jurisdição",
    "page.subtitle": "Encontre legislação de direitos autorais para bibliotecas digitais, autores e projetos de documentação em PDF",
    "lang.label": "Idioma",

    "search.label": "Pesquisar",
    "search.placeholder": "Jurisdição, lei, autoridade ou palavra-chave…",
    "search.hint": "Use \"aspas\" para uma frase exata. Códigos curtos, como US ou BR, só correspondem a palavras inteiras.",
    "search.scope": "Pesquisar em",
    "scope.all": "Todos os campos",
    "scope.name": "Jurisdição e nome da lei",
    "scope.rights": "Direitos e exceções",
    "scope.authority": "Órgão competente",

    "btn.search": "Pesquisar",
    "btn.clear": "Limpar",
    "btn.compare": "Comparar selecionadas",
    "btn.compareCount": "Comparar selecionadas ({count})",
    "btn.export": "Exportar CSV",
    "btn.close": "Fechar",

    "filters.advanced": "Filtros avançados",
    "filters.active.one": "{count} ativo",
    "filters.active.other": "{count} ativos",
    "filters.region": "Região",
    "filters.treaty": "Tratados",
    "filters.treatyHint": "Parte em todos os selecionados",
    "filters.term": "Prazo de proteção",
    "filters.ptype": "Tipo de proteção",
    "filters.clearAll": "Limpar todos os filtros",
    "filters.activeLabel": "Filtros ativos",
    "chip.country": "Jurisdição: {value}",
    "chip.treaty": "Tratado: {value}",
    "chip.term": "Prazo: {value}",
    "chip.ptype": "Tipo: {value}",
    "chip.remove": "Remover filtro {value}",

    "region.europe": "Europa",
    "region.americas": "Américas",
    "region.asia_pacific": "Ásia-Pacífico",
    "region.middle_east_africa": "Oriente Médio e África",

    "treaty.Berne": "Convenção de Berna",
    "treaty.TRIPS": "Acordo TRIPS",
    "treaty.WCT": "Tratado da OMPI sobre Direito de Autor (WCT)",
    "treaty.WPPT": "Tratado da OMPI sobre Interpretações e Fonogramas (WPPT)",

    "term.life": "Vida do autor + {years} anos",
    "term.unspecified": "Outro",

    "results.title": "Leis de direitos autorais",
    "results.summary": "{shown} de {total} leis · {countries}",
    "results.none": "Nenhuma lei corresponde a estes filtros.",
    "results.noneHint": "Remova um filtro ou pesquise com menos palavras.",

    "table.caption": "Leis de direitos autorais que correspondem aos filtros atuais",
    "table.select": "Selecionar",
    "table.selectAll": "Selecionar todos os resultados",
    "table.country": "Jurisdição",
    "table.law": "Lei",
    "table.term": "Prazo",
    "table.author": "Direitos do autor",
    "table.moral": "Direitos morais",
    "table.treaties": "Tratados",
    "table.reference": "Referência",
    "table.open": "Abrir a referência oficial",
    "table.onlyCountry": "Mostrar apenas {name}",

    "compare.title": "Comparação da proteção de direitos autorais",
    "compare.criterion": "Critério",
    "compare.protection_type": "Tipo de proteção",
    "compare.term_of_protection": "Prazo de proteção",
    "compare.author_rights": "Direitos do autor",
    "compare.moral_rights": "Direitos morais",
    "compare.orphan_works": "Obras órfãs",
    "compare.digital_protection": "Proteção digital",
    "compare.fair_use_exceptions": "Uso justo e exceções",
    "compare.registration_required": "Registro obrigatório",
    "compare.treaties_signatory": "Tratados",
    "compare.needTwo": "Selecione leis de pelo menos 2 jurisdições diferentes para comparar.",

    "chart.title": "Leis por prazo de proteção",
    "chart.subtitle": "{laws} · anos após a morte do autor",
    "chart.showTable": "Ver como tabela",
    "chart.showChart": "Ver como gráfico",
    "chart.colTerm": "Prazo",
    "chart.colLaws": "Leis",
    "chart.colDetails": "Detalhes",
    "chart.more": "+{count} mais",

    "unit.law.one": "{count} lei",
    "unit.law.other": "{count} leis",
    "unit.jurisdiction.one": "{count} jurisdição",
    "unit.jurisdiction.other": "{count} jurisdições",

    "card.region": "Região",
    "card.laws": "Leis",
    "card.treaties": "Tratados",
    "card.folder": "Pasta de dados",
    "card.json": "Baixar JSON",
    "card.csv": "Baixar CSV",
    "card.updated": "Dados atualizados",

    "footer.data": "Dados atualizados em {date} · versão {version} · {laws} leis · {countries}",
    "footer.note": "Para bibliotecas digitais, repositórios de textos e projetos de documentação em PDF. Os prazos são resumos: confira sempre a fonte oficial antes de confiar em um registro.",
    "status.error": "Não foi possível carregar a base de dados de leis de direitos autorais. Recarregue a página.",
  },
};

let current = DEFAULT_LANGUAGE;
const listeners = new Set();

/** "es", "es-MX", "spa" → "spa"; anything unsupported → null. */
export function normalizeLanguage(code) {
  if (!code) return null;
  const value = String(code).toLowerCase().trim();
  if (Object.hasOwn(LANGUAGES, value)) return value;
  const base = value.split(/[-_]/)[0];
  if (Object.hasOwn(LANGUAGES, base)) return base;
  return Object.hasOwn(BY_ISO2, base) ? BY_ISO2[base] : null;
}

/** ?lang= in the URL wins, then the stored preference, then the browser language. */
export function detectLanguage({ search = "", stored = null, preferred = [] } = {}) {
  const fromUrl = normalizeLanguage(new URLSearchParams(search).get("lang"));
  if (fromUrl) return fromUrl;
  const fromStorage = normalizeLanguage(stored);
  if (fromStorage) return fromStorage;
  for (const candidate of preferred) {
    const lang = normalizeLanguage(candidate);
    if (lang) return lang;
  }
  return DEFAULT_LANGUAGE;
}

export const getLanguage = () => current;
export const getLocale = () => BCP47[current];

export function t(key, params) {
  const text = TRANSLATIONS[current][key] ?? TRANSLATIONS[DEFAULT_LANGUAGE][key] ?? key;
  if (!params) return text;
  return text.replace(/\{(\w+)\}/g, (match, name) => (name in params ? String(params[name]) : match));
}

/** Picks "<key>.one" / "<key>.other" with Intl.PluralRules for the current language. */
export function plural(key, count, params = {}) {
  const category = new Intl.PluralRules(BCP47[current]).select(count);
  const form = `${key}.${category}` in TRANSLATIONS[current] ? category : "other";
  return t(`${key}.${form}`, { count, ...params });
}

export function formatDate(iso) {
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return String(iso ?? "");
  return new Intl.DateTimeFormat(BCP47[current], { dateStyle: "medium" }).format(date);
}

export function applyTranslations(root = document) {
  root.querySelectorAll("[data-i18n]").forEach((node) => {
    node.textContent = t(node.dataset.i18n);
  });
  for (const attribute of ["placeholder", "aria-label", "title"]) {
    root.querySelectorAll(`[data-i18n-${attribute}]`).forEach((node) => {
      node.setAttribute(attribute, t(node.getAttribute(`data-i18n-${attribute}`)));
    });
  }
  document.title = t("page.title");
}

function readStored() {
  try {
    return localStorage.getItem(STORAGE_KEY);
  } catch {
    return null;
  }
}

export function setLanguage(code, { persist = true } = {}) {
  current = normalizeLanguage(code) ?? DEFAULT_LANGUAGE;
  document.documentElement.lang = BCP47[current];
  document.documentElement.dataset.lang = current;
  applyTranslations();
  if (persist) {
    try {
      localStorage.setItem(STORAGE_KEY, current);
    } catch {
      // storage can be unavailable (private mode); the choice then lasts for this page only
    }
  }
  listeners.forEach((listener) => listener(current));
  return current;
}

export function initLanguage() {
  const lang = detectLanguage({
    search: location.search,
    stored: readStored(),
    preferred: navigator.languages ?? [navigator.language],
  });
  return setLanguage(lang, { persist: false });
}

export function onLanguageChange(listener) {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

export function bindLanguageSelect(select) {
  if (!select.options.length) {
    for (const [code, name] of Object.entries(LANGUAGES)) {
      const option = new Option(name, code);
      option.lang = BCP47[code];
      select.add(option);
    }
  }
  select.value = current;
  select.addEventListener("change", () => setLanguage(select.value));
  onLanguageChange((lang) => {
    select.value = lang;
  });
}
