const translations = {
  en: {
    "search.country": "Search by country name...",
    "filter.region": "All Regions",
    "filter.lawType": "All Types",
    "btn.search": "Search",
    "btn.clear": "Clear",
    "btn.compare": "Compare Selected",
    "btn.export": "📥 Export CSV",
    "results.empty":
      "Enter search criteria and click \"Search\" to view privacy laws",
    "comparison.title": "Law Comparison",
    "timeline.title": "Implementation Timeline",
  },
  es: {
    "search.country": "Buscar por nombre de país...",
    "filter.region": "Todas las Regiones",
    "filter.lawType": "Todos los Tipos",
    "btn.search": "Buscar",
    "btn.clear": "Limpiar",
    "btn.compare": "Comparar Seleccionados",
    "btn.export": "📥 Exportar CSV",
    "results.empty":
      "Ingrese criterios de búsqueda y haga clic en \"Buscar\" para ver leyes de privacidad",
    "comparison.title": "Comparación de Leyes",
    "timeline.title": "Cronología de Implementación",
  },
  fr: {
    "search.country": "Rechercher par nom de pays...",
    "filter.region": "Toutes les Régions",
    "filter.lawType": "Tous les Types",
    "btn.search": "Rechercher",
    "btn.clear": "Effacer",
    "btn.compare": "Comparer la Sélection",
    "btn.export": "📥 Exporter CSV",
    "results.empty":
      "Entrez les critères de recherche et cliquez sur \"Rechercher\" pour afficher les lois de confidentialité",
    "comparison.title": "Comparaison des Lois",
    "timeline.title": "Chronologie de Mise en Œuvre",
  },
  de: {
    "search.country": "Nach Ländername suchen...",
    "filter.region": "Alle Regionen",
    "filter.lawType": "Alle Typen",
    "btn.search": "Suchen",
    "btn.clear": "Löschen",
    "btn.compare": "Ausgewählte Vergleichen",
    "btn.export": "📥 CSV Exportieren",
    "results.empty":
      "Geben Sie Suchkriterien ein und klicken Sie auf \"Suchen\", um Datenschutzgesetze anzuzeigen",
    "comparison.title": "Gesetzesverglichnung",
    "timeline.title": "Implementierungs-Chronologie",
  },
  pt: {
    "search.country": "Pesquisar por nome do país...",
    "filter.region": "Todas as Regiões",
    "filter.lawType": "Todos os Tipos",
    "btn.search": "Pesquisar",
    "btn.clear": "Limpar",
    "btn.compare": "Comparar Selecionados",
    "btn.export": "📥 Exportar CSV",
    "results.empty":
      "Digite os critérios de pesquisa e clique em \"Pesquisar\" para visualizar leis de privacidade",
    "comparison.title": "Comparação de Leis",
    "timeline.title": "Cronograma de Implementação",
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
