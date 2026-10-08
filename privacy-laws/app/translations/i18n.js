// UI translations. Languages are handled with three-letter ISO 639-2 codes
// (eng, spa, fra, deu, por); <html lang> receives the BCP 47 equivalent.
// Law data (names, requirements…) is shown as published and is not translated.

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
    "page.title": "Privacy Laws by Country",
    "page.heading": "Privacy Laws by Country",
    "page.subtitle": "Find privacy legislation and regulations for genealogy, compliance, and research",
    "lang.label": "Language",

    "search.label": "Search",
    "search.placeholder": "Country, law, authority or keyword…",
    "search.hint": "Use \"quotes\" for an exact phrase. Short codes such as US or BR match whole words only.",
    "search.scope": "Search in",
    "scope.all": "All fields",
    "scope.name": "Country & law name",
    "scope.authority": "Authorities",
    "scope.requirements": "Scope & requirements",
    "scope.penalties": "Penalties",

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
    "filters.framework": "Frameworks & treaties",
    "filters.frameworkHint": "The country must take part in all selected",
    "filters.year": "In force (year)",
    "filters.yearFrom": "From",
    "filters.yearTo": "To",
    "filters.language": "Reference language",
    "filters.anyLanguage": "Any language",
    "filters.clearAll": "Clear all filters",
    "filters.activeLabel": "Active filters",
    "chip.country": "Country: {value}",
    "chip.framework": "Framework: {value}",
    "chip.year": "In force: {value}",
    "chip.language": "Language: {value}",
    "chip.remove": "Remove filter {value}",

    "region.europe": "Europe",
    "region.americas": "Americas",
    "region.asia_pacific": "Asia-Pacific",
    "region.middle_east_africa": "Middle East & Africa",

    "results.title": "Privacy laws",
    "results.summary": "{shown} of {total} laws · {countries}",
    "results.none": "No laws match these filters.",
    "results.noneHint": "Remove a filter or search for fewer words.",

    "table.caption": "Privacy laws matching the current filters",
    "table.select": "Select",
    "table.selectAll": "Select all results",
    "table.country": "Country",
    "table.law": "Law",
    "table.enacted": "Enacted",
    "table.effective": "In force",
    "table.upcoming": "Not yet in force",
    "table.authority": "Enforcement authority",
    "table.penalties": "Penalties",
    "table.reference": "Reference",
    "table.open": "Open official reference",
    "table.onlyCountry": "Show only {name}",

    "compare.title": "Law comparison",
    "compare.criterion": "Criterion",
    "compare.scope": "Scope",
    "compare.applies_to": "Applies to",
    "compare.key_requirements": "Key requirements",
    "compare.data_categories": "Data categories",
    "compare.retention_period": "Retention period",
    "compare.penalties_range": "Penalties",
    "compare.exemptions": "Exemptions",
    "compare.frameworks": "Frameworks & treaties",
    "compare.needTwo": "Select laws from at least 2 different countries to compare.",

    "chart.title": "Laws entering into force, by year",
    "chart.subtitle": "{laws} · {span}",
    "chart.showTable": "View as table",
    "chart.showChart": "View as chart",
    "chart.colYear": "Year",
    "chart.colLaws": "Laws",
    "chart.colDetails": "Details",
    "chart.more": "+{count} more",

    "unit.law.one": "{count} law",
    "unit.law.other": "{count} laws",
    "unit.country.one": "{count} country",
    "unit.country.other": "{count} countries",

    "card.region": "Region",
    "card.frameworks": "Frameworks",
    "framework.GDPR": "EU General Data Protection Regulation (applies in EU/EEA states)",
    "framework.EU-Adequacy": "European Commission adequacy decision (may cover only some sectors or certified companies)",
    "framework.CoE-108": "Council of Europe Convention 108 (data protection)",
    "framework.APEC-CBPR": "APEC / Global Cross-Border Privacy Rules (CBPR) system",
    "card.laws": "Laws",
    "card.folder": "Data folder",
    "card.json": "Download JSON",
    "card.csv": "Download CSV",
    "card.updated": "Data updated",

    "footer.data": "Data updated {date} · version {version} · {laws} laws · {countries}",
    "footer.note": "For genealogy platforms and privacy compliance research. Always check the official source before relying on an entry.",
    "status.error": "Could not load the privacy laws database. Please reload the page.",
  },

  spa: {
    "page.title": "Leyes de privacidad por país",
    "page.heading": "Leyes de privacidad por país",
    "page.subtitle": "Encuentra legislación y normativa de privacidad para genealogía, cumplimiento e investigación",
    "lang.label": "Idioma",

    "search.label": "Buscar",
    "search.placeholder": "País, ley, autoridad o palabra clave…",
    "search.hint": "Usa \"comillas\" para una frase exacta. Los códigos cortos, como US o BR, solo coinciden con palabras completas.",
    "search.scope": "Buscar en",
    "scope.all": "Todos los campos",
    "scope.name": "País y nombre de la ley",
    "scope.authority": "Autoridades",
    "scope.requirements": "Ámbito y requisitos",
    "scope.penalties": "Sanciones",

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
    "filters.framework": "Marcos y tratados",
    "filters.frameworkHint": "El país debe participar en todos los seleccionados",
    "filters.year": "En vigor (año)",
    "filters.yearFrom": "Desde",
    "filters.yearTo": "Hasta",
    "filters.language": "Idioma de referencia",
    "filters.anyLanguage": "Cualquier idioma",
    "filters.clearAll": "Quitar todos los filtros",
    "filters.activeLabel": "Filtros activos",
    "chip.country": "País: {value}",
    "chip.framework": "Marco: {value}",
    "chip.year": "En vigor: {value}",
    "chip.language": "Idioma: {value}",
    "chip.remove": "Quitar filtro {value}",

    "region.europe": "Europa",
    "region.americas": "América",
    "region.asia_pacific": "Asia-Pacífico",
    "region.middle_east_africa": "Oriente Medio y África",

    "results.title": "Leyes de privacidad",
    "results.summary": "{shown} de {total} leyes · {countries}",
    "results.none": "Ninguna ley coincide con estos filtros.",
    "results.noneHint": "Quita algún filtro o busca con menos palabras.",

    "table.caption": "Leyes de privacidad que cumplen los filtros actuales",
    "table.select": "Seleccionar",
    "table.selectAll": "Seleccionar todos los resultados",
    "table.country": "País",
    "table.law": "Ley",
    "table.enacted": "Promulgación",
    "table.effective": "En vigor",
    "table.upcoming": "Aún no en vigor",
    "table.authority": "Autoridad competente",
    "table.penalties": "Sanciones",
    "table.reference": "Referencia",
    "table.open": "Abrir la referencia oficial",
    "table.onlyCountry": "Mostrar solo {name}",

    "compare.title": "Comparación de leyes",
    "compare.criterion": "Criterio",
    "compare.scope": "Ámbito",
    "compare.applies_to": "Se aplica a",
    "compare.key_requirements": "Requisitos clave",
    "compare.data_categories": "Categorías de datos",
    "compare.retention_period": "Plazo de conservación",
    "compare.penalties_range": "Sanciones",
    "compare.exemptions": "Exenciones",
    "compare.frameworks": "Marcos y tratados",
    "compare.needTwo": "Selecciona leyes de al menos 2 países distintos para compararlas.",

    "chart.title": "Leyes que entran en vigor, por año",
    "chart.subtitle": "{laws} · {span}",
    "chart.showTable": "Ver como tabla",
    "chart.showChart": "Ver como gráfico",
    "chart.colYear": "Año",
    "chart.colLaws": "Leyes",
    "chart.colDetails": "Detalle",
    "chart.more": "+{count} más",

    "unit.law.one": "{count} ley",
    "unit.law.other": "{count} leyes",
    "unit.country.one": "{count} país",
    "unit.country.other": "{count} países",

    "card.region": "Región",
    "card.frameworks": "Marcos",
    "framework.GDPR": "Reglamento General de Protección de Datos de la UE (se aplica en los Estados de la UE/EEE)",
    "framework.EU-Adequacy": "Decisión de adecuación de la Comisión Europea (puede cubrir solo ciertos sectores o empresas certificadas)",
    "framework.CoE-108": "Convenio 108 del Consejo de Europa (protección de datos)",
    "framework.APEC-CBPR": "Sistema de Reglas Transfronterizas de Privacidad (CBPR) de APEC / Global",
    "card.laws": "Leyes",
    "card.folder": "Carpeta de datos",
    "card.json": "Descargar JSON",
    "card.csv": "Descargar CSV",
    "card.updated": "Datos actualizados",

    "footer.data": "Datos actualizados el {date} · versión {version} · {laws} leyes · {countries}",
    "footer.note": "Para plataformas de genealogía e investigación de cumplimiento de privacidad. Comprueba siempre la fuente oficial antes de basarte en un registro.",
    "status.error": "No se pudo cargar la base de datos de leyes de privacidad. Recarga la página.",
  },

  fra: {
    "page.title": "Lois sur la vie privée par pays",
    "page.heading": "Lois sur la vie privée par pays",
    "page.subtitle": "Trouvez la législation et la réglementation sur la vie privée pour la généalogie, la conformité et la recherche",
    "lang.label": "Langue",

    "search.label": "Rechercher",
    "search.placeholder": "Pays, loi, autorité ou mot-clé…",
    "search.hint": "Utilisez des « guillemets » pour une expression exacte. Les codes courts comme US ou BR ne correspondent qu'à des mots entiers.",
    "search.scope": "Rechercher dans",
    "scope.all": "Tous les champs",
    "scope.name": "Pays et nom de la loi",
    "scope.authority": "Autorités",
    "scope.requirements": "Champ d'application et exigences",
    "scope.penalties": "Sanctions",

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
    "filters.framework": "Cadres et traités",
    "filters.frameworkHint": "Le pays doit participer à tous ceux qui sont sélectionnés",
    "filters.year": "En vigueur (année)",
    "filters.yearFrom": "De",
    "filters.yearTo": "À",
    "filters.language": "Langue de référence",
    "filters.anyLanguage": "Toutes les langues",
    "filters.clearAll": "Effacer tous les filtres",
    "filters.activeLabel": "Filtres actifs",
    "chip.country": "Pays : {value}",
    "chip.framework": "Cadre : {value}",
    "chip.year": "En vigueur : {value}",
    "chip.language": "Langue : {value}",
    "chip.remove": "Retirer le filtre {value}",

    "region.europe": "Europe",
    "region.americas": "Amériques",
    "region.asia_pacific": "Asie-Pacifique",
    "region.middle_east_africa": "Moyen-Orient et Afrique",

    "results.title": "Lois sur la vie privée",
    "results.summary": "{shown} sur {total} lois · {countries}",
    "results.none": "Aucune loi ne correspond à ces filtres.",
    "results.noneHint": "Retirez un filtre ou cherchez avec moins de mots.",

    "table.caption": "Lois sur la vie privée correspondant aux filtres actuels",
    "table.select": "Sélectionner",
    "table.selectAll": "Sélectionner tous les résultats",
    "table.country": "Pays",
    "table.law": "Loi",
    "table.enacted": "Promulgation",
    "table.effective": "Entrée en vigueur",
    "table.upcoming": "Pas encore en vigueur",
    "table.authority": "Autorité de contrôle",
    "table.penalties": "Sanctions",
    "table.reference": "Référence",
    "table.open": "Ouvrir la référence officielle",
    "table.onlyCountry": "Afficher uniquement {name}",

    "compare.title": "Comparaison des lois",
    "compare.criterion": "Critère",
    "compare.scope": "Champ d'application",
    "compare.applies_to": "S'applique à",
    "compare.key_requirements": "Exigences clés",
    "compare.data_categories": "Catégories de données",
    "compare.retention_period": "Durée de conservation",
    "compare.penalties_range": "Sanctions",
    "compare.exemptions": "Exemptions",
    "compare.frameworks": "Cadres et traités",
    "compare.needTwo": "Sélectionnez des lois d'au moins 2 pays différents pour les comparer.",

    "chart.title": "Lois entrant en vigueur, par année",
    "chart.subtitle": "{laws} · {span}",
    "chart.showTable": "Voir sous forme de tableau",
    "chart.showChart": "Voir sous forme de graphique",
    "chart.colYear": "Année",
    "chart.colLaws": "Lois",
    "chart.colDetails": "Détails",
    "chart.more": "+{count} autres",

    "unit.law.one": "{count} loi",
    "unit.law.other": "{count} lois",
    "unit.country.one": "{count} pays",
    "unit.country.other": "{count} pays",

    "card.region": "Région",
    "card.frameworks": "Cadres",
    "framework.GDPR": "Règlement général sur la protection des données de l’UE (applicable dans les États de l’UE/EEE)",
    "framework.EU-Adequacy": "Décision d’adéquation de la Commission européenne (peut ne couvrir que certains secteurs ou entreprises certifiées)",
    "framework.CoE-108": "Convention 108 du Conseil de l’Europe (protection des données)",
    "framework.APEC-CBPR": "Système de règles transfrontières de protection de la vie privée (CBPR) de l’APEC / Global",
    "card.laws": "Lois",
    "card.folder": "Dossier de données",
    "card.json": "Télécharger le JSON",
    "card.csv": "Télécharger le CSV",
    "card.updated": "Données mises à jour",

    "footer.data": "Données mises à jour le {date} · version {version} · {laws} lois · {countries}",
    "footer.note": "Pour les plateformes de généalogie et la recherche en conformité vie privée. Vérifiez toujours la source officielle avant de vous appuyer sur une entrée.",
    "status.error": "Impossible de charger la base de données des lois sur la vie privée. Rechargez la page.",
  },

  deu: {
    "page.title": "Datenschutzgesetze nach Land",
    "page.heading": "Datenschutzgesetze nach Land",
    "page.subtitle": "Datenschutzgesetze und -vorschriften für Genealogie, Compliance und Recherche finden",
    "lang.label": "Sprache",

    "search.label": "Suchen",
    "search.placeholder": "Land, Gesetz, Behörde oder Stichwort…",
    "search.hint": "Mit „Anführungszeichen“ nach einer exakten Wendung suchen. Kurze Kürzel wie US oder BR treffen nur ganze Wörter.",
    "search.scope": "Suchen in",
    "scope.all": "Alle Felder",
    "scope.name": "Land und Gesetzesname",
    "scope.authority": "Behörden",
    "scope.requirements": "Anwendungsbereich und Anforderungen",
    "scope.penalties": "Sanktionen",

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
    "filters.framework": "Rahmenwerke & Abkommen",
    "filters.frameworkHint": "Das Land muss an allen ausgewählten teilnehmen",
    "filters.year": "In Kraft (Jahr)",
    "filters.yearFrom": "Von",
    "filters.yearTo": "Bis",
    "filters.language": "Referenzsprache",
    "filters.anyLanguage": "Beliebige Sprache",
    "filters.clearAll": "Alle Filter zurücksetzen",
    "filters.activeLabel": "Aktive Filter",
    "chip.country": "Land: {value}",
    "chip.framework": "Rahmenwerk: {value}",
    "chip.year": "In Kraft: {value}",
    "chip.language": "Sprache: {value}",
    "chip.remove": "Filter {value} entfernen",

    "region.europe": "Europa",
    "region.americas": "Amerika",
    "region.asia_pacific": "Asien-Pazifik",
    "region.middle_east_africa": "Naher Osten und Afrika",

    "results.title": "Datenschutzgesetze",
    "results.summary": "{shown} von {total} Gesetzen · {countries}",
    "results.none": "Keine Gesetze entsprechen diesen Filtern.",
    "results.noneHint": "Entfernen Sie einen Filter oder suchen Sie mit weniger Wörtern.",

    "table.caption": "Datenschutzgesetze, die den aktuellen Filtern entsprechen",
    "table.select": "Auswählen",
    "table.selectAll": "Alle Ergebnisse auswählen",
    "table.country": "Land",
    "table.law": "Gesetz",
    "table.enacted": "Erlassen",
    "table.effective": "In Kraft",
    "table.upcoming": "Noch nicht in Kraft",
    "table.authority": "Aufsichtsbehörde",
    "table.penalties": "Sanktionen",
    "table.reference": "Quelle",
    "table.open": "Offizielle Quelle öffnen",
    "table.onlyCountry": "Nur {name} anzeigen",

    "compare.title": "Gesetzesvergleich",
    "compare.criterion": "Kriterium",
    "compare.scope": "Anwendungsbereich",
    "compare.applies_to": "Gilt für",
    "compare.key_requirements": "Wesentliche Anforderungen",
    "compare.data_categories": "Datenkategorien",
    "compare.retention_period": "Aufbewahrungsfrist",
    "compare.penalties_range": "Sanktionen",
    "compare.exemptions": "Ausnahmen",
    "compare.frameworks": "Rahmenwerke & Abkommen",
    "compare.needTwo": "Wählen Sie Gesetze aus mindestens 2 verschiedenen Ländern zum Vergleichen aus.",

    "chart.title": "Inkrafttreten von Gesetzen pro Jahr",
    "chart.subtitle": "{laws} · {span}",
    "chart.showTable": "Als Tabelle anzeigen",
    "chart.showChart": "Als Diagramm anzeigen",
    "chart.colYear": "Jahr",
    "chart.colLaws": "Gesetze",
    "chart.colDetails": "Details",
    "chart.more": "+{count} weitere",

    "unit.law.one": "{count} Gesetz",
    "unit.law.other": "{count} Gesetze",
    "unit.country.one": "{count} Land",
    "unit.country.other": "{count} Länder",

    "card.region": "Region",
    "card.frameworks": "Rahmenwerke",
    "framework.GDPR": "EU-Datenschutz-Grundverordnung (gilt in den EU/EWR-Staaten)",
    "framework.EU-Adequacy": "Angemessenheitsbeschluss der Europäischen Kommission (kann nur bestimmte Sektoren oder zertifizierte Unternehmen erfassen)",
    "framework.CoE-108": "Übereinkommen 108 des Europarats (Datenschutz)",
    "framework.APEC-CBPR": "APEC- bzw. Global-CBPR-System (Cross-Border Privacy Rules)",
    "card.laws": "Gesetze",
    "card.folder": "Datenordner",
    "card.json": "JSON herunterladen",
    "card.csv": "CSV herunterladen",
    "card.updated": "Daten aktualisiert",

    "footer.data": "Daten aktualisiert am {date} · Version {version} · {laws} Gesetze · {countries}",
    "footer.note": "Für Genealogie-Plattformen und die Recherche zur Datenschutz-Compliance. Prüfen Sie stets die offizielle Quelle, bevor Sie sich auf einen Eintrag verlassen.",
    "status.error": "Die Datenschutzgesetz-Datenbank konnte nicht geladen werden. Bitte laden Sie die Seite neu.",
  },

  por: {
    "page.title": "Leis de privacidade por país",
    "page.heading": "Leis de privacidade por país",
    "page.subtitle": "Encontre legislação e regulamentos de privacidade para genealogia, conformidade e pesquisa",
    "lang.label": "Idioma",

    "search.label": "Pesquisar",
    "search.placeholder": "País, lei, autoridade ou palavra-chave…",
    "search.hint": "Use \"aspas\" para uma frase exata. Códigos curtos, como US ou BR, só correspondem a palavras inteiras.",
    "search.scope": "Pesquisar em",
    "scope.all": "Todos os campos",
    "scope.name": "País e nome da lei",
    "scope.authority": "Autoridades",
    "scope.requirements": "Âmbito e requisitos",
    "scope.penalties": "Sanções",

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
    "filters.framework": "Marcos e tratados",
    "filters.frameworkHint": "O país deve participar de todos os selecionados",
    "filters.year": "Em vigor (ano)",
    "filters.yearFrom": "De",
    "filters.yearTo": "Até",
    "filters.language": "Idioma de referência",
    "filters.anyLanguage": "Qualquer idioma",
    "filters.clearAll": "Limpar todos os filtros",
    "filters.activeLabel": "Filtros ativos",
    "chip.country": "País: {value}",
    "chip.framework": "Marco: {value}",
    "chip.year": "Em vigor: {value}",
    "chip.language": "Idioma: {value}",
    "chip.remove": "Remover filtro {value}",

    "region.europe": "Europa",
    "region.americas": "Américas",
    "region.asia_pacific": "Ásia-Pacífico",
    "region.middle_east_africa": "Oriente Médio e África",

    "results.title": "Leis de privacidade",
    "results.summary": "{shown} de {total} leis · {countries}",
    "results.none": "Nenhuma lei corresponde a estes filtros.",
    "results.noneHint": "Remova um filtro ou pesquise com menos palavras.",

    "table.caption": "Leis de privacidade que correspondem aos filtros atuais",
    "table.select": "Selecionar",
    "table.selectAll": "Selecionar todos os resultados",
    "table.country": "País",
    "table.law": "Lei",
    "table.enacted": "Promulgação",
    "table.effective": "Em vigor",
    "table.upcoming": "Ainda não em vigor",
    "table.authority": "Autoridade fiscalizadora",
    "table.penalties": "Sanções",
    "table.reference": "Referência",
    "table.open": "Abrir a referência oficial",
    "table.onlyCountry": "Mostrar apenas {name}",

    "compare.title": "Comparação de leis",
    "compare.criterion": "Critério",
    "compare.scope": "Âmbito",
    "compare.applies_to": "Aplica-se a",
    "compare.key_requirements": "Requisitos principais",
    "compare.data_categories": "Categorias de dados",
    "compare.retention_period": "Prazo de conservação",
    "compare.penalties_range": "Sanções",
    "compare.exemptions": "Isenções",
    "compare.frameworks": "Marcos e tratados",
    "compare.needTwo": "Selecione leis de pelo menos 2 países diferentes para comparar.",

    "chart.title": "Leis que entram em vigor, por ano",
    "chart.subtitle": "{laws} · {span}",
    "chart.showTable": "Ver como tabela",
    "chart.showChart": "Ver como gráfico",
    "chart.colYear": "Ano",
    "chart.colLaws": "Leis",
    "chart.colDetails": "Detalhes",
    "chart.more": "+{count} mais",

    "unit.law.one": "{count} lei",
    "unit.law.other": "{count} leis",
    "unit.country.one": "{count} país",
    "unit.country.other": "{count} países",

    "card.region": "Região",
    "card.frameworks": "Marcos",
    "framework.GDPR": "Regulamento Geral de Proteção de Dados da UE (aplica-se nos Estados da UE/EEE)",
    "framework.EU-Adequacy": "Decisão de adequação da Comissão Europeia (pode abranger apenas certos setores ou empresas certificadas)",
    "framework.CoE-108": "Convenção 108 do Conselho da Europa (proteção de dados)",
    "framework.APEC-CBPR": "Sistema de Regras Transfronteiriças de Privacidade (CBPR) da APEC / Global",
    "card.laws": "Leis",
    "card.folder": "Pasta de dados",
    "card.json": "Baixar JSON",
    "card.csv": "Baixar CSV",
    "card.updated": "Dados atualizados",

    "footer.data": "Dados atualizados em {date} · versão {version} · {laws} leis · {countries}",
    "footer.note": "Para plataformas de genealogia e pesquisa de conformidade em privacidade. Confira sempre a fonte oficial antes de confiar em um registro.",
    "status.error": "Não foi possível carregar a base de dados de leis de privacidade. Recarregue a página.",
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
