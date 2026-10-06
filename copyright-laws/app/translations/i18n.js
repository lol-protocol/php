const translations = {
  en: {
    "search.jurisdiction": "Search by jurisdiction...",
    "filter.region": "All Regions",
    "filter.materialType": "All Materials",
    "btn.search": "Search",
    "btn.clear": "Clear",
    "btn.compare": "Compare Selected",
    "btn.export": "📥 Export CSV",
    "results.empty":
      "Enter search criteria and click \"Search\" to view copyright laws",
    "comparison.title": "Copyright Protection Comparison",
    "material.title": "Protection by Material Type",
  },
  es: {
    "search.jurisdiction": "Buscar por jurisdicción...",
    "filter.region": "Todas las Regiones",
    "filter.materialType": "Todos los Materiales",
    "btn.search": "Buscar",
    "btn.clear": "Limpiar",
    "btn.compare": "Comparar Seleccionados",
    "btn.export": "📥 Exportar CSV",
    "results.empty":
      "Ingrese criterios de búsqueda y haga clic en \"Buscar\" para ver leyes de derechos de autor",
    "comparison.title": "Comparación de Protección de Derechos de Autor",
    "material.title": "Protección por Tipo de Material",
  },
  fr: {
    "search.jurisdiction": "Rechercher par juridiction...",
    "filter.region": "Toutes les Régions",
    "filter.materialType": "Tous les Matériaux",
    "btn.search": "Rechercher",
    "btn.clear": "Effacer",
    "btn.compare": "Comparer la Sélection",
    "btn.export": "📥 Exporter CSV",
    "results.empty":
      "Entrez les critères de recherche et cliquez sur \"Rechercher\" pour afficher les lois sur les droits d'auteur",
    "comparison.title": "Comparaison de la Protection des Droits d'Auteur",
    "material.title": "Protection par Type de Matériau",
  },
  de: {
    "search.jurisdiction": "Nach Rechtsprechung suchen...",
    "filter.region": "Alle Regionen",
    "filter.materialType": "Alle Materialien",
    "btn.search": "Suchen",
    "btn.clear": "Löschen",
    "btn.compare": "Ausgewählte Vergleichen",
    "btn.export": "📥 CSV Exportieren",
    "results.empty":
      "Geben Sie Suchkriterien ein und klicken Sie auf \"Suchen\", um Urheberrechtsgesetze anzuzeigen",
    "comparison.title": "Vergleich des Urheberrechtsschutzes",
    "material.title": "Schutz nach Materialtyp",
  },
  pt: {
    "search.jurisdiction": "Pesquisar por jurisdição...",
    "filter.region": "Todas as Regiões",
    "filter.materialType": "Todos os Materiais",
    "btn.search": "Pesquisar",
    "btn.clear": "Limpar",
    "btn.compare": "Comparar Selecionados",
    "btn.export": "📥 Exportar CSV",
    "results.empty":
      "Digite os critérios de pesquisa e clique em \"Pesquisar\" para visualizar leis de direitos autorais",
    "comparison.title": "Comparação de Proteção de Direitos Autorais",
    "material.title": "Proteção por Tipo de Material",
  },
};

let currentLang = "en";

export async function loadTranslations(lang = "en") {
  currentLang = lang;
  updateUIText();
}

export function t(key) {
  return translations[currentLang]?.[key] || translations.en[key] || key;
}

function updateUIText() {
  // This can be extended to update DOM elements based on translations
  // For now, it's a placeholder for future localization of UI elements
}
