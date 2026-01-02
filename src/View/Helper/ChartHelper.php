<?php
declare(strict_types=1);

namespace Crustum\Rhythm\View\Helper;

use Cake\View\Helper;

/**
 * Chart Helper
 *
 * Provides generic chart configuration for Rhythm widgets.
 */
class ChartHelper extends Helper
{
    /**
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'defaultColor' => '#4A5568',
        'sparkline' => [
            'color' => '#888888',
            'bodyColor' => '#374151',
            'backgroundColor' => '#fff',
        ],
        'line' => [
            'titleColor' => '#374151',
            'bodyColor' => '#374151',
            'borderColor' => '#d1d5db',
            'backgroundColor' => '#fff',
        ],
    ];

    /**
     * Create a line chart configuration for Rhythm widgets
     *
     * @param string $name Chart name
     * @param array $data Chart data (series => [timestamp => value])
     * @param array $options Additional options (colors, label, unit, etc.)
     * @return array
     */
    public function createLineChart(string $name, array $data, array $options = []): array
    {
        $colors = $options['colors'] ?? [];
        $label = $options['label'] ?? $name;
        $unit = $options['unit'] ?? '';
        $titleColor = $options['titleColor'] ?? $this->getConfig('line.titleColor');
        $lineColor = $options['lineColor'] ?? $this->getConfig('line.color');
        $bodyColor = $options['bodyColor'] ?? $this->getConfig('line.bodyColor');
        $backgroundColor = $options['backgroundColor'] ?? $this->getConfig('line.backgroundColor');

        $chartColors = [];
        foreach (array_keys($data) as $series) {
            $chartColors[] = $colors[$series] ?? $this->getConfig('defaultColor');
        }

        $chartJsOptions = [
            'height' => 80,
            'width' => '100%',
            'responsive' => true,
            'maintainAspectRatio' => false,
            'elements' => [
                'point' => ['radius' => 0],
            ],
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => [
                    'enabled' => true,
                    'mode' => 'index',
                    'intersect' => false,
                    'backgroundColor' => $backgroundColor,
                    'titleColor' => $titleColor,
                    'bodyColor' => $bodyColor,
                    'borderColor' => $lineColor,
                    'borderWidth' => 1,
                    'displayColors' => true,
                    'padding' => 8,
                    'caretSize' => 4,
                    'cornerRadius' => 4,
                    'titleFont' => ['weight' => 'bold'],
                    'bodyFont' => ['weight' => 'normal'],
                ],
            ],
            'layout' => [
                'padding' => [
                    'top' => 5,
                    'bottom' => 5,
                    'left' => 5,
                    'right' => 5,
                ],
            ],
            'scales' => [
                'x' => ['display' => false],
                'y' => [
                    'display' => true,
                    'beginAtZero' => true,
                    'ticks' => [
                        'display' => false,
                        'maxTicksLimit' => 3,
                    ],
                    'grid' => [
                        'display' => false,
                    ],
                    'border' => [
                        'display' => false,
                    ],
                ],
            ],
        ];
        $chartJsOptions = array_merge_recursive($chartJsOptions, $options['chartJs'] ?? []);

        return $this->buildChartConfig($name, $data, [
            'label' => $label,
            'unit' => $unit,
            'colors' => $chartColors,
            'chartJs' => $chartJsOptions,
        ]);
    }

    /**
     * Create a sparkline chart configuration.
     *
     * @param string $name Chart name
     * @param array $data Chart data
     * @param array $options Additional options (borderColor, label, unit, etc.)
     * @return array
     */
    public function createSparklineChart(string $name, array $data, array $options = []): array
    {
        $borderColor = $options['borderColor'] ?? $options['color'] ?? $this->getConfig('sparkline.color');
        $bodyColor = $options['bodyColor'] ?? $this->getConfig('sparkline.bodyColor');
        $backgroundColor = $options['backgroundColor'] ?? $this->getConfig('sparkline.backgroundColor');
        $label = $options['label'] ?? $name;
        $unit = $options['unit'] ?? '';

        $chartJsOptions = [
            'height' => 24,
            'width' => 120,
            'responsive' => true,
            'maintainAspectRatio' => false,
            'elements' => [
                'point' => ['radius' => 0],
            ],
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => [
                    'enabled' => true,
                    'mode' => 'index',
                    'intersect' => false,
                    'backgroundColor' => $backgroundColor,
                    'titleColor' => $borderColor,
                    'bodyColor' => $bodyColor,
                    'borderColor' => $borderColor,
                    'borderWidth' => 1,
                    'displayColors' => false,
                    'padding' => 8,
                    'caretSize' => 4,
                    'cornerRadius' => 4,
                    'titleFont' => ['weight' => 'bold'],
                    'bodyFont' => ['weight' => 'normal'],
                ],
            ],
            'scales' => [
                'x' => ['display' => false],
                'y' => ['display' => false],
            ],
        ];

        $chartJsOptions = array_merge_recursive($chartJsOptions, $options['chartJs'] ?? []);

        $datasetOptions = [
            'borderWidth' => 2,
            'borderCapStyle' => 'round',
            'pointHitRadius' => 10,
            'pointStyle' => false,
            'tension' => 0.2,
            'spanGaps' => false,
        ];

        return $this->buildChartConfig($name, $data, [
            'label' => $label,
            'unit' => $unit,
            'borderColor' => $borderColor,
            'colors' => $options['colors'] ?? null,
            'dataset' => array_merge($datasetOptions, $options['dataset'] ?? []),
            'chartJs' => $chartJsOptions,
        ]);
    }

    /**
     * Build the common chart configuration structure.
     *
     * @param string $name Chart name
     * @param array $data Chart data
     * @param array $options Chart options
     * @return array
     */
    protected function buildChartConfig(string $name, array $data, array $options): array
    {
        return [
            'name' => $name,
            'type' => 'line',
            'data' => $data,
            'options' => $options,
        ];
    }

    /**
     * Build legend array with color assignment for each item
     *
     * @param array $legendData Legend data from widget (label, value/total)
     * @param array $options Additional options (colors, defaultColor)
     * @return array
     */
    public function buildLegend(array $legendData, array $options = []): array
    {
        $colors = $options['colors'] ?? [];
        $defaultColor = $options['defaultColor'] ?? $this->getConfig('defaultColor');

        $result = [];
        foreach ($legendData as $key => $item) {
            $label = $item['label'] ?? $key;
            $color = $colors[$label] ?? $defaultColor;
            $result[] = [
                'label' => $label,
                'color' => $color,
                'value' => $item['value'] ?? ($item['total'] ?? null),
            ];
        }

        return $result;
    }
}
