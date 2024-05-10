<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\QueryLanguage\Pql;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\Pql\Lexer;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\Pql\Parser;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\PqlAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\FetchIdsBySearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\ElementServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexEntityService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;

/**
 * @internal
 */
final class ParserTest extends Unit
{
    public function testParseComparison(): void
    {
        $this->assertQueryResult(
            'color = "red"',
            [
                'match' => ['color' => 'red'],
            ]
        );

        $this->assertQueryResult(
            'price > 27',
            [
                'range' => ['price' => ['gt' => 27]],
            ]
        );

        $this->assertQueryResult(
            'price < 30',
            [
                'range' => ['price' => ['lt' => 30]],
            ]
        );

        $this->assertQueryResult(
            'price >= 27',
            [
                'range' => ['price' => ['gte' => 27]],
            ]
        );

        $this->assertQueryResult(
            'price <= 30',
            [
                'range' => ['price' => ['lte' => 30]],
            ]
        );

        $this->assertQueryResult(
            'name like "Jaguar*"',
            [
                'wildcard' => ['name' => 'Jaguar?'],
            ]
        );

        $this->assertQueryResult(
            'name like "Jag*ar*"',
            [
                'wildcard' => ['name' => 'Jag?ar?'],
            ]
        );

        $this->assertQueryResult(
            'name like "Jaguar"',
            [
                'wildcard' => ['name' => 'Jaguar'],
            ]
        );
    }

    public function testParseExpression(): void
    {

        $this->assertQueryResult(
            'color = "red" or series = "E-Type"',
            [
                'bool' => [
                    'should' => [
                        ['match' => ['color' => 'red']],
                        ['match' => ['series' => 'E-Type']],
                    ],
                    'minimum_should_match' => 1,
                ],
            ]
        );

        $this->assertQueryResult(
            '(color = "red" or series = "E-Type")',
            [
                'bool' => [
                    'should' => [
                        ['match' => ['color' => 'red']],
                        ['match' => ['series' => 'E-Type']],
                    ],
                    'minimum_should_match' => 1,
                ],
            ]
        );

        $this->assertQueryResult(
            '(color = "red" or series = "E-Type") and name = "Jaguar"',

            [
                'bool' => [
                    'must' => [
                        [
                            'bool' => [
                                'should' => [
                                    ['match' => ['color' => 'red']],
                                    ['match' => ['series' => 'E-Type']],
                                ],
                                'minimum_should_match' => 1,
                            ],
                        ],
                        ['match' => ['name' => 'Jaguar']],
                    ],
                ],
            ]
        );
        $this->assertQueryResult(
            'color = "red" or series = "E-Type" and name = "Jaguar"',

            [
                'bool' => [
                    'must' => [
                        [
                            'bool' => [
                                'should' => [
                                    ['match' => ['color' => 'red']],
                                    ['match' => ['series' => 'E-Type']],
                                ],
                                'minimum_should_match' => 1,
                            ],
                        ],
                        ['match' => ['name' => 'Jaguar']],
                    ],
                ],
            ]
        );

        $this->assertQueryResult(
            'color = "red" or (series = "E-Type" and name = "Jaguar")',
            [
                'bool' => [
                    'should' => [
                        ['match' => ['color' => 'red']],
                        [
                            'bool' => [
                                'must' => [
                                    ['match' => ['series' => 'E-Type']],
                                    ['match' => ['name' => 'Jaguar']],
                                ],
                            ],
                        ],
                    ],
                    'minimum_should_match' => 1,
                ],
            ]
        );

        $this->assertQueryResult(
            'color = "red" or ((series = "E-Type" and name = "Jaguar") or price > 100)',
            [
                'bool' => [
                    'should' => [
                        ['match' => ['color' => 'red']],
                        [
                            'bool' => [
                                'should' => [
                                    [
                                        'bool' => [
                                            'must' => [
                                                ['match' => ['series' => 'E-Type']],
                                                ['match' => ['name' => 'Jaguar']],
                                            ],
                                        ],
                                    ],
                                    ['range' => ['price' => ['gt' => 100]]],
                                ],
                                'minimum_should_match' => 1,
                            ],
                        ],
                    ],
                    'minimum_should_match' => 1,
                ],
            ]
        );
    }

    public function testParseQueryString(): void
    {
        $this->assertQueryResult(
            'Query("color:(red or blue)")',
            [
                'query_string' => [
                    'query' => 'color:(red or blue)',
                ],
            ]
        );

        $this->assertQueryResult(
            'series="Jaguar" and Query("color:(red or blue)")',
            [
                'bool' => [
                    'must' => [
                        ['match' => ['series' => 'Jaguar']],
                        [
                            'query_string' => [
                                'query' => 'color:(red or blue)',
                            ],
                        ],
                    ],
                ],
            ]
        );

        $this->assertQueryResult(
            '(Query("color:(red or blue)") and price>1000.23)',
            [
                'bool' => [
                    'must' => [
                        [
                            'query_string' => [
                                'query' => 'color:(red or blue)',
                            ],
                        ],
                        ['range' => ['price' => ['gt' => 1000.23]]],
                    ],
                ],
            ]
        );
    }

    private function assertQueryResult(string $query, array $result): void
    {
        $parser = $this->createParser();
        $lexer = new Lexer();
        $lexer->setQuery($query);
        $tokens = $lexer->getTokens();
        $parser = $parser->apply($query, $tokens, $this->getCarMapping());
        $parseResult = $parser->parse();
        $this->assertEmpty($parseResult->getSubQueries());
        $this->assertSame($result, $parseResult->getQuery());
    }

    private function createParser(): Parser
    {
        $indexEntityService = new IndexEntityService(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->makeEmpty(ElementServiceInterface::class),
        );

        $pqlAdapter = new PqlAdapter(
            $indexEntityService,
            $this->makeEmpty(FetchIdsBySearchServiceInterface::class),
            []
        );

        return new Parser(
            $pqlAdapter,
            $indexEntityService
        );
    }

    private function getCarMapping(): array
    {
        $mapping = <<<JSON
{
  "pimcore_car-odd": {
    "mappings": {
      "properties": {
        "custom_fields": {
          "properties": {
            "PortalEngineBundle": {
              "properties": {
                "standard_fields": {
                  "properties": {
                    "bodyStyle": {
                      "properties": {
                        "id": {
                          "type": "long"
                        },
                        "name": {
                          "type": "text",
                          "fields": {
                            "keyword": {
                              "type": "keyword"
                            }
                          }
                        },
                        "type": {
                          "type": "keyword"
                        }
                      }
                    },
                    "categories": {
                      "properties": {
                        "id": {
                          "type": "long"
                        },
                        "name": {
                          "type": "text",
                          "fields": {
                            "keyword": {
                              "type": "keyword"
                            }
                          }
                        },
                        "type": {
                          "type": "keyword"
                        }
                      }
                    },
                    "manufacturer": {
                      "properties": {
                        "id": {
                          "type": "long"
                        },
                        "name": {
                          "type": "text",
                          "fields": {
                            "keyword": {
                              "type": "keyword"
                            }
                          }
                        },
                        "type": {
                          "type": "keyword"
                        }
                      }
                    }
                  }
                },
                "system_fields": {
                  "properties": {
                    "collections": {
                      "type": "keyword"
                    },
                    "name": {
                      "properties": {
                        "de": {
                          "type": "keyword"
                        },
                        "en": {
                          "type": "keyword"
                        },
                        "fr": {
                          "type": "keyword"
                        }
                      }
                    },
                    "publicShares": {
                      "type": "keyword"
                    },
                    "thumbnail": {
                      "type": "keyword"
                    }
                  }
                }
              }
            },
            "assetDependencies": {
              "type": "integer"
            }
          }
        },
        "standard_fields": {
          "properties": {
            "attributes": {
              "properties": {
                "Bodywork": {
                  "properties": {
                    "cargoCapacity": {
                      "properties": {
                        "unitId": {
                          "type": "text"
                        },
                        "value": {
                          "type": "float"
                        }
                      }
                    },
                    "numberOfDoors": {
                      "type": "float"
                    },
                    "numberOfSeats": {
                      "type": "float"
                    }
                  }
                },
                "Dimensions": {
                  "properties": {
                    "length": {
                      "properties": {
                        "unitId": {
                          "type": "text"
                        },
                        "value": {
                          "type": "float"
                        }
                      }
                    },
                    "weight": {
                      "properties": {
                        "unitId": {
                          "type": "text"
                        },
                        "value": {
                          "type": "float"
                        }
                      }
                    },
                    "wheelbase": {
                      "properties": {
                        "unitId": {
                          "type": "text"
                        },
                        "value": {
                          "type": "float"
                        }
                      }
                    },
                    "width": {
                      "properties": {
                        "unitId": {
                          "type": "text"
                        },
                        "value": {
                          "type": "float"
                        }
                      }
                    }
                  }
                },
                "Engine": {
                  "properties": {
                    "capacity": {
                      "properties": {
                        "unitId": {
                          "type": "text"
                        },
                        "value": {
                          "type": "float"
                        }
                      }
                    },
                    "cylinders": {
                      "type": "float"
                    },
                    "engineLocation": {
                      "type": "text",
                      "fields": {
                        "analyzed": {
                          "type": "text",
                          "analyzer": "standard",
                          "search_analyzer": "generic_data_index_whitespace_analyzer"
                        },
                        "analyzed_ngram": {
                          "type": "text",
                          "analyzer": "generic_data_index_ngram_analyzer",
                          "search_analyzer": "generic_data_index_whitespace_analyzer"
                        },
                        "keyword": {
                          "type": "keyword"
                        }
                      }
                    },
                    "power": {
                      "properties": {
                        "unitId": {
                          "type": "text"
                        },
                        "value": {
                          "type": "float"
                        }
                      }
                    }
                  }
                },
                "Transmission": {
                  "properties": {
                    "wheelDrive": {
                      "type": "text",
                      "fields": {
                        "analyzed": {
                          "type": "text",
                          "analyzer": "standard",
                          "search_analyzer": "generic_data_index_whitespace_analyzer"
                        },
                        "analyzed_ngram": {
                          "type": "text",
                          "analyzer": "generic_data_index_ngram_analyzer",
                          "search_analyzer": "generic_data_index_whitespace_analyzer"
                        },
                        "keyword": {
                          "type": "keyword"
                        }
                      }
                    }
                  }
                }
              }
            },
            "bodyStyle": {
              "properties": {
                "asset": {
                  "type": "long"
                },
                "document": {
                  "type": "long"
                },
                "object": {
                  "type": "long"
                }
              }
            },
            "carClass": {
              "type": "text",
              "fields": {
                "analyzed": {
                  "type": "text",
                  "analyzer": "standard",
                  "search_analyzer": "generic_data_index_whitespace_analyzer"
                },
                "analyzed_ngram": {
                  "type": "text",
                  "analyzer": "generic_data_index_ngram_analyzer",
                  "search_analyzer": "generic_data_index_whitespace_analyzer"
                },
                "keyword": {
                  "type": "keyword"
                }
              }
            },
            "categories": {
              "properties": {
                "asset": {
                  "type": "long"
                },
                "document": {
                  "type": "long"
                },
                "object": {
                  "type": "long"
                }
              }
            },
            "color": {
              "type": "text",
              "fields": {
                "analyzed": {
                  "type": "text",
                  "analyzer": "standard",
                  "search_analyzer": "generic_data_index_whitespace_analyzer"
                },
                "analyzed_ngram": {
                  "type": "text",
                  "analyzer": "generic_data_index_ngram_analyzer",
                  "search_analyzer": "generic_data_index_whitespace_analyzer"
                },
                "keyword": {
                  "type": "keyword"
                }
              }
            },
            "country": {
              "type": "text",
              "fields": {
                "analyzed": {
                  "type": "text",
                  "analyzer": "standard",
                  "search_analyzer": "generic_data_index_whitespace_analyzer"
                },
                "analyzed_ngram": {
                  "type": "text",
                  "analyzer": "generic_data_index_ngram_analyzer",
                  "search_analyzer": "generic_data_index_whitespace_analyzer"
                },
                "keyword": {
                  "type": "keyword"
                }
              }
            },
            "description": {
              "properties": {
                "de": {
                  "type": "text",
                  "fields": {
                    "analyzed": {
                      "type": "text",
                      "analyzer": "standard",
                      "search_analyzer": "generic_data_index_whitespace_analyzer"
                    },
                    "analyzed_ngram": {
                      "type": "text",
                      "analyzer": "generic_data_index_ngram_analyzer",
                      "search_analyzer": "generic_data_index_whitespace_analyzer"
                    },
                    "keyword": {
                      "type": "keyword"
                    }
                  }
                },
                "en": {
                  "type": "text",
                  "fields": {
                    "analyzed": {
                      "type": "text",
                      "analyzer": "standard",
                      "search_analyzer": "generic_data_index_whitespace_analyzer"
                    },
                    "analyzed_ngram": {
                      "type": "text",
                      "analyzer": "generic_data_index_ngram_analyzer",
                      "search_analyzer": "generic_data_index_whitespace_analyzer"
                    },
                    "keyword": {
                      "type": "keyword"
                    }
                  }
                },
                "fr": {
                  "type": "text",
                  "fields": {
                    "analyzed": {
                      "type": "text",
                      "analyzer": "standard",
                      "search_analyzer": "generic_data_index_whitespace_analyzer"
                    },
                    "analyzed_ngram": {
                      "type": "text",
                      "analyzer": "generic_data_index_ngram_analyzer",
                      "search_analyzer": "generic_data_index_whitespace_analyzer"
                    },
                    "keyword": {
                      "type": "keyword"
                    }
                  }
                }
              }
            },
            "gallery": {
              "properties": {
                "assets": {
                  "type": "long"
                },
                "details": {
                  "type": "nested",
                  "properties": {
                    "crop": {
                      "properties": {
                        "cropHeight": {
                          "type": "float"
                        },
                        "cropLeft": {
                          "type": "float"
                        },
                        "cropPercent": {
                          "type": "boolean"
                        },
                        "cropTop": {
                          "type": "float"
                        },
                        "cropWidth": {
                          "type": "float"
                        }
                      }
                    },
                    "hotspots": {
                      "type": "nested",
                      "properties": {
                        "data": {
                          "type": "flat_object"
                        },
                        "height": {
                          "type": "float"
                        },
                        "left": {
                          "type": "float"
                        },
                        "name": {
                          "type": "text",
                          "fields": {
                            "analyzed": {
                              "type": "text",
                              "analyzer": "standard",
                              "search_analyzer": "generic_data_index_whitespace_analyzer"
                            },
                            "analyzed_ngram": {
                              "type": "text",
                              "analyzer": "generic_data_index_ngram_analyzer",
                              "search_analyzer": "generic_data_index_whitespace_analyzer"
                            },
                            "keyword": {
                              "type": "keyword"
                            }
                          }
                        },
                        "top": {
                          "type": "float"
                        },
                        "width": {
                          "type": "float"
                        }
                      }
                    },
                    "image": {
                      "properties": {
                        "id": {
                          "type": "long"
                        },
                        "type": {
                          "type": "keyword"
                        }
                      }
                    },
                    "marker": {
                      "type": "nested",
                      "properties": {
                        "data": {
                          "type": "flat_object"
                        },
                        "left": {
                          "type": "float"
                        },
                        "name": {
                          "type": "text",
                          "fields": {
                            "analyzed": {
                              "type": "text",
                              "analyzer": "standard",
                              "search_analyzer": "generic_data_index_whitespace_analyzer"
                            },
                            "analyzed_ngram": {
                              "type": "text",
                              "analyzer": "generic_data_index_ngram_analyzer",
                              "search_analyzer": "generic_data_index_whitespace_analyzer"
                            },
                            "keyword": {
                              "type": "keyword"
                            }
                          }
                        },
                        "top": {
                          "type": "float"
                        }
                      }
                    }
                  }
                }
              }
            },
            "genericImages": {
              "properties": {
                "assets": {
                  "type": "long"
                },
                "details": {
                  "type": "nested",
                  "properties": {
                    "crop": {
                      "properties": {
                        "cropHeight": {
                          "type": "float"
                        },
                        "cropLeft": {
                          "type": "float"
                        },
                        "cropPercent": {
                          "type": "boolean"
                        },
                        "cropTop": {
                          "type": "float"
                        },
                        "cropWidth": {
                          "type": "float"
                        }
                      }
                    },
                    "hotspots": {
                      "type": "nested",
                      "properties": {
                        "data": {
                          "type": "flat_object"
                        },
                        "height": {
                          "type": "float"
                        },
                        "left": {
                          "type": "float"
                        },
                        "name": {
                          "type": "text",
                          "fields": {
                            "analyzed": {
                              "type": "text",
                              "analyzer": "standard",
                              "search_analyzer": "generic_data_index_whitespace_analyzer"
                            },
                            "analyzed_ngram": {
                              "type": "text",
                              "analyzer": "generic_data_index_ngram_analyzer",
                              "search_analyzer": "generic_data_index_whitespace_analyzer"
                            },
                            "keyword": {
                              "type": "keyword"
                            }
                          }
                        },
                        "top": {
                          "type": "float"
                        },
                        "width": {
                          "type": "float"
                        }
                      }
                    },
                    "image": {
                      "properties": {
                        "id": {
                          "type": "long"
                        },
                        "type": {
                          "type": "keyword"
                        }
                      }
                    },
                    "marker": {
                      "type": "nested",
                      "properties": {
                        "data": {
                          "type": "flat_object"
                        },
                        "left": {
                          "type": "float"
                        },
                        "name": {
                          "type": "text",
                          "fields": {
                            "analyzed": {
                              "type": "text",
                              "analyzer": "standard",
                              "search_analyzer": "generic_data_index_whitespace_analyzer"
                            },
                            "analyzed_ngram": {
                              "type": "text",
                              "analyzer": "generic_data_index_ngram_analyzer",
                              "search_analyzer": "generic_data_index_whitespace_analyzer"
                            },
                            "keyword": {
                              "type": "keyword"
                            }
                          }
                        },
                        "top": {
                          "type": "float"
                        }
                      }
                    }
                  }
                }
              }
            },
            "location": {
              "properties": {
                "latitude": {
                  "type": "float"
                },
                "longitude": {
                  "type": "float"
                }
              }
            },
            "mainImageTest": {
              "properties": {
                "id": {
                  "type": "long"
                },
                "type": {
                  "type": "keyword"
                }
              }
            },
            "manufacturer": {
              "properties": {
                "asset": {
                  "type": "long"
                },
                "document": {
                  "type": "long"
                },
                "object": {
                  "type": "long"
                }
              }
            },
            "name": {
              "properties": {
                "de": {
                  "type": "text",
                  "fields": {
                    "analyzed": {
                      "type": "text",
                      "analyzer": "standard",
                      "search_analyzer": "generic_data_index_whitespace_analyzer"
                    },
                    "analyzed_ngram": {
                      "type": "text",
                      "analyzer": "generic_data_index_ngram_analyzer",
                      "search_analyzer": "generic_data_index_whitespace_analyzer"
                    },
                    "keyword": {
                      "type": "keyword"
                    }
                  }
                },
                "en": {
                  "type": "text",
                  "fields": {
                    "analyzed": {
                      "type": "text",
                      "analyzer": "standard",
                      "search_analyzer": "generic_data_index_whitespace_analyzer"
                    },
                    "analyzed_ngram": {
                      "type": "text",
                      "analyzer": "generic_data_index_ngram_analyzer",
                      "search_analyzer": "generic_data_index_whitespace_analyzer"
                    },
                    "keyword": {
                      "type": "keyword"
                    }
                  }
                },
                "fr": {
                  "type": "text",
                  "fields": {
                    "analyzed": {
                      "type": "text",
                      "analyzer": "standard",
                      "search_analyzer": "generic_data_index_whitespace_analyzer"
                    },
                    "analyzed_ngram": {
                      "type": "text",
                      "analyzer": "generic_data_index_ngram_analyzer",
                      "search_analyzer": "generic_data_index_whitespace_analyzer"
                    },
                    "keyword": {
                      "type": "keyword"
                    }
                  }
                }
              }
            },
            "objectType": {
              "type": "text",
              "fields": {
                "analyzed": {
                  "type": "text",
                  "analyzer": "standard",
                  "search_analyzer": "generic_data_index_whitespace_analyzer"
                },
                "analyzed_ngram": {
                  "type": "text",
                  "analyzer": "generic_data_index_ngram_analyzer",
                  "search_analyzer": "generic_data_index_whitespace_analyzer"
                },
                "keyword": {
                  "type": "keyword"
                }
              }
            },
            "productionYear": {
              "type": "float"
            },
            "saleInformation": {
              "properties": {
                "SaleInformation": {
                  "properties": {
                    "availabilityPieces": {
                      "type": "float"
                    },
                    "availabilityType": {
                      "type": "text",
                      "fields": {
                        "analyzed": {
                          "type": "text",
                          "analyzer": "standard",
                          "search_analyzer": "generic_data_index_whitespace_analyzer"
                        },
                        "analyzed_ngram": {
                          "type": "text",
                          "analyzer": "generic_data_index_ngram_analyzer",
                          "search_analyzer": "generic_data_index_whitespace_analyzer"
                        },
                        "keyword": {
                          "type": "keyword"
                        }
                      }
                    },
                    "condition": {
                      "type": "text",
                      "fields": {
                        "analyzed": {
                          "type": "text",
                          "analyzer": "standard",
                          "search_analyzer": "generic_data_index_whitespace_analyzer"
                        },
                        "analyzed_ngram": {
                          "type": "text",
                          "analyzer": "generic_data_index_ngram_analyzer",
                          "search_analyzer": "generic_data_index_whitespace_analyzer"
                        },
                        "keyword": {
                          "type": "keyword"
                        }
                      }
                    },
                    "milage": {
                      "properties": {
                        "unitId": {
                          "type": "text"
                        },
                        "value": {
                          "type": "float"
                        }
                      }
                    },
                    "priceInEUR": {
                      "type": "float"
                    }
                  }
                }
              }
            },
            "series": {
              "type": "text",
              "fields": {
                "analyzed": {
                  "type": "text",
                  "analyzer": "standard",
                  "search_analyzer": "generic_data_index_whitespace_analyzer"
                },
                "analyzed_ngram": {
                  "type": "text",
                  "analyzer": "generic_data_index_ngram_analyzer",
                  "search_analyzer": "generic_data_index_whitespace_analyzer"
                },
                "keyword": {
                  "type": "keyword"
                }
              }
            },
            "urlSlug": {
              "type": "nested",
              "properties": {
                "siteId": {
                  "type": "keyword"
                },
                "slug": {
                  "type": "text"
                }
              }
            }
          }
        },
        "system_fields": {
          "properties": {
            "checksum": {
              "type": "long"
            },
            "className": {
              "type": "text",
              "fields": {
                "keyword": {
                  "type": "keyword",
                  "ignore_above": 256
                }
              }
            },
            "classname": {
              "type": "keyword"
            },
            "creationDate": {
              "type": "date"
            },
            "fullPath": {
              "type": "text",
              "fields": {
                "keyword": {
                  "type": "keyword"
                }
              },
              "analyzer": "generic_data_index_path_analyzer"
            },
            "hasWorkflowWithPermissions": {
              "type": "boolean"
            },
            "id": {
              "type": "long"
            },
            "isLocked": {
              "type": "boolean"
            },
            "key": {
              "type": "keyword",
              "fields": {
                "analyzed": {
                  "type": "text",
                  "analyzer": "standard",
                  "search_analyzer": "generic_data_index_whitespace_analyzer"
                },
                "analyzed_ngram": {
                  "type": "text",
                  "analyzer": "generic_data_index_ngram_analyzer",
                  "search_analyzer": "generic_data_index_whitespace_analyzer"
                }
              }
            },
            "lock": {
              "type": "keyword"
            },
            "modificationDate": {
              "type": "date"
            },
            "parentId": {
              "type": "long"
            },
            "parentTags": {
              "type": "integer"
            },
            "path": {
              "type": "text",
              "fields": {
                "keyword": {
                  "type": "keyword"
                }
              },
              "analyzer": "generic_data_index_path_analyzer"
            },
            "pathLevel": {
              "type": "integer"
            },
            "pathLevels": {
              "type": "nested",
              "properties": {
                "level": {
                  "type": "integer"
                },
                "name": {
                  "type": "keyword"
                }
              }
            },
            "published": {
              "type": "boolean"
            },
            "tags": {
              "type": "integer"
            },
            "thumbnail": {
              "type": "keyword"
            },
            "type": {
              "type": "keyword"
            },
            "userModification": {
              "type": "integer"
            },
            "userOwner": {
              "type": "integer"
            }
          }
        }
      }
    }
  }
}
JSON;

        return json_decode($mapping, true);
    }
}
