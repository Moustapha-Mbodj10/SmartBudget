// Graphiques Chart.js du tableau de bord et des statistiques (CDC §3.6, §7.6).
// Les données viennent du serveur : <script type="application/json" id="donnees-graphiques">.
// Style : barres en pilule dont le mois mis en valeur est plein et les autres en teinte douce, anneau pour les catégories.
document.addEventListener('DOMContentLoaded', function () {
    var source = document.getElementById('donnees-graphiques');
    if (!source || typeof Chart === 'undefined') { return; }

    var donnees = JSON.parse(source.textContent);

    var ENCRE = '#14171c';
    var GRIS = '#7c828c';
    var VERT = '#14a05b';
    var VERT_DOUX = '#bfe0ce';
    var BLEU = '#054086';
    var ROUGE = '#e04b5a';
    var ROUGE_DOUX = '#f6c5cb';

    Chart.defaults.font.family = "'Urbanist', system-ui, -apple-system, 'Segoe UI', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.font.weight = 600;
    Chart.defaults.color = GRIS;
    // Préférence système « réduire les animations » : les graphiques apparaissent directement
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { Chart.defaults.animation = false; }

    // Comme format_fcfa() côté serveur : aucune décimale pour un montant entier, deux sinon (119 500,50)
    var formater = function (valeur) {
        var d = Number.isInteger(valeur) ? 0 : 2;
        return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: d, maximumFractionDigits: d }).format(valeur);
    };
    // Intl utilise des espaces insécables fines : on les remplace par des espaces simples (« 150 000 FCFA »)
    var fcfa = function (valeur) { return formater(valeur).replace(/[  ]/g, ' ') + ' FCFA'; };

    var infobulle = {
        backgroundColor: ENCRE,
        titleColor: '#fff',
        bodyColor: '#fff',
        padding: 12,
        cornerRadius: 14,
        boxPadding: 6,
        titleFont: { weight: 700 },
        bodyFont: { weight: 600 }
    };

    // Anneau des dépenses par catégorie : la famille des verts, puis des neutres et un jaune chaud
    var PALETTE = ['#054086', '#52eba8', '#0b5bc4', '#14171c', '#f2b84b', '#8fb0e0', '#868b95', '#e04b5a', '#14a05b', '#d9dbe1'];

    var anneau = document.getElementById('graphique-repartition');
    if (anneau && donnees.repartition.length) {
        new Chart(anneau, {
            type: 'doughnut',
            data: {
                labels: donnees.repartition.map(function (l) { return l.categorie; }),
                datasets: [{
                    data: donnees.repartition.map(function (l) { return l.total; }),
                    backgroundColor: donnees.repartition.map(function (_, i) { return PALETTE[i % PALETTE.length]; }),
                    borderColor: '#ffffff',
                    borderWidth: 4,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, boxHeight: 8, padding: 14 } },
                    tooltip: Object.assign({}, infobulle, {
                        callbacks: {
                            label: function (ctx) {
                                var ligne = donnees.repartition[ctx.dataIndex];
                                return ' ' + ctx.label + ' : ' + fcfa(ligne.total) + ' (' + String(ligne.pourcentage).replace('.', ',') + ' %)';
                            }
                        }
                    })
                }
            }
        });
    }

    // Courbe lissée de l'évolution du solde cumulé, avec un dégradé vert sous la ligne et le dernier point mis en valeur
    var courbe = document.getElementById('graphique-solde');
    if (courbe && donnees.evolution) {
        var ctxCourbe = courbe.getContext('2d');
        var degrade = ctxCourbe.createLinearGradient(0, 0, 0, courbe.clientHeight || 290);
        degrade.addColorStop(0, 'rgba(5, 64, 134, 0.28)');
        degrade.addColorStop(1, 'rgba(5, 64, 134, 0)');
        var dernier = donnees.evolution.valeurs.length - 1;

        new Chart(courbe, {
            type: 'line',
            data: {
                labels: donnees.evolution.labels,
                datasets: [{
                    label: 'Solde cumulé',
                    data: donnees.evolution.valeurs,
                    borderColor: BLEU,
                    borderWidth: 3,
                    backgroundColor: degrade,
                    fill: true,
                    tension: 0.4,
                    pointRadius: donnees.evolution.valeurs.map(function (_, i) { return i === dernier ? 6 : 0; }),
                    pointHoverRadius: 6,
                    pointBackgroundColor: BLEU,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { grid: { display: false }, border: { display: false } },
                    y: {
                        border: { display: false },
                        grid: { color: '#eceef1', borderDash: [4, 4] },
                        ticks: { callback: function (v) { return formater(v).replace(/[  ]/g, ' '); } }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: Object.assign({}, infobulle, {
                        displayColors: false,
                        callbacks: { label: function (ctx) { return 'Solde : ' + fcfa(ctx.parsed.y); } }
                    })
                }
            }
        });
    }

    // Barres : le mois mis en valeur (surligne) est plein, les autres en teinte douce
    var barres = document.getElementById('graphique-mensuel');
    if (barres) {
        var surligne = donnees.serie.surligne;
        var couleurs = function (plein, doux) {
            return donnees.serie.labels.map(function (_, i) { return (surligne === null || surligne === undefined || i === surligne) ? plein : doux; });
        };

        new Chart(barres, {
            type: 'bar',
            data: {
                labels: donnees.serie.labels,
                datasets: [
                    { label: 'Revenus', data: donnees.serie.revenus, backgroundColor: couleurs(VERT, VERT_DOUX), hoverBackgroundColor: VERT, borderRadius: 12, borderSkipped: false, maxBarThickness: 26 },
                    { label: 'Dépenses', data: donnees.serie.depenses, backgroundColor: couleurs(ROUGE, ROUGE_DOUX), hoverBackgroundColor: ROUGE, borderRadius: 12, borderSkipped: false, maxBarThickness: 26 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { grid: { display: false }, border: { display: false } },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: '#eceef1', borderDash: [4, 4] },
                        ticks: { callback: function (v) { return formater(v).replace(/[  ]/g, ' '); } }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true, pointStyle: 'circle', boxWidth: 8, boxHeight: 8, padding: 14,
                            // La pastille de légende porte la couleur pleine, pas la teinte douce des mois passés
                            generateLabels: function (chart) {
                                return chart.data.datasets.map(function (ds, i) {
                                    return { text: ds.label, fillStyle: ds.hoverBackgroundColor, strokeStyle: ds.hoverBackgroundColor, pointStyle: 'circle', hidden: !chart.isDatasetVisible(i), datasetIndex: i };
                                });
                            }
                        }
                    },
                    tooltip: Object.assign({}, infobulle, {
                        callbacks: {
                            label: function (ctx) { return ' ' + ctx.dataset.label + ' : ' + fcfa(ctx.parsed.y); },
                            labelColor: function (ctx) {
                                var plein = ctx.dataset.hoverBackgroundColor;
                                return { borderColor: plein, backgroundColor: plein, borderRadius: 6 };
                            }
                        }
                    })
                }
            }
        });
    }
});
