<?php


declare(strict_types = 1);

namespace Tests\Service;

use App\Service\TextComparator;
use PHPUnit\Framework\TestCase;

class TextComparatorTest extends TestCase
{
	private TextComparator $comparator;

	protected function setUp(): void
	{
		$this->comparator = new TextComparator();
	}

	public function testIdenticalTextsReturnOnlyUnchanged(): void
	{
		$result = $this->comparator->compare('Привет мир.', 'Привет мир.');

		$this->assertSame(
			[['type' => 'unchanged', 'text' => 'Привет мир.']],
			$result
		);
	}

	public function testEmptyOldTextReturnsAllAdded(): void
	{
		$result = $this->comparator->compare('', 'Новый текст. Ещё предложение.');

		$this->assertSame(
			[
				['type' => 'added', 'text' => 'Новый текст.'],
				['type' => 'added', 'text' => 'Ещё предложение.'],
			],
			$result
		);
	}

	public function testEmptyNewTextReturnsAllDeleted(): void
	{
		$result = $this->comparator->compare('Старый текст. Ещё одно.', '');

		$this->assertSame(
			[
				['type' => 'deleted', 'text' => 'Старый текст.'],
				['type' => 'deleted', 'text' => 'Ещё одно.'],
			],
			$result
		);
	}

	public function testBothTextsEmptyReturnsEmptyArray(): void
	{
		$result = $this->comparator->compare('', '');

		$this->assertSame([], $result);
	}

	public function testSimilarSentenceIsDetectedAsModified(): void
	{
		// Предложения похожи по словам (общие: "кот", "на", "птиц") —
		// должны склеиться в одно "modified", а не delete+insert по отдельности
		$old = 'Кот сидел на окне и смотрел на птиц.';
		$new = 'Кот сидел на подоконнике и смотрел на птиц.';

		$result = $this->comparator->compare($old, $new);

		$this->assertCount(1, $result);
		$this->assertSame('modified', $result[0]['type']);
		$this->assertSame($new, $result[0]['text']);
		$this->assertSame($old, $result[0]['old']);
	}

	public function testCompletelyDifferentSentencesAreDeletedAndAdded(): void
	{
		// Предложения не имеют общих слов — не должны склеиться в modified
		$old = 'Собака лает во дворе.';
		$new = 'Компьютер завис снова.';

		$result = $this->comparator->compare($old, $new);

		$this->assertCount(2, $result);
		$this->assertSame('deleted', $result[0]['type']);
		$this->assertSame($old, $result[0]['text']);
		$this->assertSame('added', $result[1]['type']);
		$this->assertSame($new, $result[1]['text']);
	}

	public function testMixedChangesPreserveUnchangedSentences(): void
	{
		$old = 'Кот сидел на окне. Он смотрел на птиц. Было тихо и спокойно.';
		$new = 'Кот сидел на подоконнике и смотрел на птиц. Было тихо. Собака лаяла во дворе.';

		$result = $this->comparator->compare($old, $new);

		// Первое и второе предложения похожи -> modified,
		// "Было тихо и спокойно." -> "Было тихо." тоже похожи -> modified,
		// третье старое предложение теряет пару -> deleted,
		// "Собака лаяла во дворе." не имеет пары -> added
		$types = array_column($result, 'type');

		$this->assertContains('modified', $types);
		$this->assertContains('deleted', $types);
		$this->assertContains('added', $types);
	}

	public function testSentenceSplittingHandlesMultiplePunctuation(): void
	{
		$result = $this->comparator->compare(
			'Что происходит?! Это невероятно...',
			'Что происходит?! Это невероятно...'
		);

		$this->assertCount(2, $result);
		$this->assertSame('unchanged', $result[0]['type']);
		$this->assertSame('unchanged', $result[1]['type']);
	}
}