<?php

declare(strict_types = 1);

namespace App\Service;

/**
 * Сравнение двух версий текста на уровне предложений.
 *
 * Общая идея работы:
 * 1. Оба текста режем на предложения.
 * 2. Ищем, какие предложения совпадают в обеих версиях (алгоритм LCS).
 * 3. Всё, что НЕ совпало, делим на "было и пропало" (delete) и "появилось" (insert).
 * 4. Пытаемся понять, не является ли пара "удалено + добавлено" на самом деле
 *    ОДНИМ предложением, которое просто немного переписали (modified).
 */
class TextComparator
{
	// Порог "похожести" двух предложений (от 0 до 1). Оценка между "изменено" и "новое"
	private const float SIMILARITY_THRESHOLD = 0.35;

	/**
	 * Главный метод — точка входа. Его вызывает контроллер.
	 *
	 * @return array<int, array{type:string, text:string, old?:string}>
	 */
	public function compare(string $oldText, string $newText): array
	{
		// текст в массив предложений
		$oldSentences = $this->splitIntoSentences($oldText);
		$newSentences = $this->splitIntoSentences($newText);

		$ops = $this->diff($oldSentences, $newSentences);

		return $this->collapseModified($ops);
	}

	/**
	 * Разбивает текст на предложения по знакам . ! ? …
	 *
	 * @return string[]
	 */
	private function splitIntoSentences(string $text): array
	{
		$text = trim($text);
		if($text === '')
		{
			return [];
		}

		// Схлопываем несколько пробелов/переносов строк в один пробел,
		$text = preg_replace('/\s+/u', ' ', $text) ?? $text;

		preg_match_all('/.*?(?:[.!?…]+(?=\s|$)|$)/us', $text, $matches);

		$sentences = [];
		foreach($matches[0] as $chunk)
		{
			$chunk = trim($chunk);
			if($chunk !== '')
			{
				// Отбрасываем пустые куски, которые может дать регулярка
				$sentences[] = $chunk;
			}
		}

		return $sentences;
	}

	/**
	 * Классический алгоритм LCS (longest common subsequence) —
	 * поиск наибольшей общей подпоследовательности двух массивов.
	 *
	 * Простыми словами: находит, какие предложения из старого текста
	 * встречаются в новом тексте в том же порядке (не обязательно подряд),
	 * и на основе этого строит список операций equal/delete/insert.
	 *
	 * @param string[] $a старые предложения
	 * @param string[] $b новые предложения
	 *
	 * @return array<int, array{op:string, text:string}>
	 */
	private function diff(array $a, array $b): array
	{
		$m = count($a); // сколько предложений в старом тексте
		$n = count($b); // сколько предложений в новом тексте

		$L = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

		for($i = $m - 1; $i >= 0; $i--)
		{
			for($j = $n - 1; $j >= 0; $j--)
			{
				if($a[$i] === $b[$j])
				{
					// Предложения совпали — берём результат для следующей
					// пары индексов и прибавляем 1 (само это предложение)
					$L[$i][$j] = $L[$i + 1][$j + 1] + 1;
				}
				else
				{
					// Не совпали — берём лучший результат из двух вариантов:
					// "пропустить предложение в a" или "пропустить в b"
					$L[$i][$j] = max($L[$i + 1][$j], $L[$i][$j + 1]);
				}
			}
		}

		// Теперь идём по таблице от начала к концу и восстанавливаем
		// реальную последовательность действий (equal/delete/insert),
		// опираясь на то, какие значения в таблице были "выгоднее"
		$ops = [];
		$i   = 0;
		$j   = 0;
		while($i < $m && $j < $n)
		{
			if($a[$i] === $b[$j])
			{
				// Предложения одинаковые в обоих текстах — не меняли
				$ops[] = ['op' => 'equal', 'text' => $a[$i]];
				$i++;
				$j++;
			}
			elseif($L[$i + 1][$j] >= $L[$i][$j + 1])
			{
				// Выгоднее считать, что предложение a[i] было удалено
				$ops[] = ['op' => 'delete', 'text' => $a[$i]];
				$i++;
			}
			else
			{
				// Выгоднее считать, что предложение b[j] было добавлено
				$ops[] = ['op' => 'insert', 'text' => $b[$j]];
				$j++;
			}
		}
		// Если один из массивов кончился раньше — дописываем "хвост"
		// оставшихся предложений как чистые удаления/добавления
		while($i < $m)
		{
			$ops[] = ['op' => 'delete', 'text' => $a[$i]];
			$i++;
		}
		while($j < $n)
		{
			$ops[] = ['op' => 'insert', 'text' => $b[$j]];
			$j++;
		}

		return $ops;
	}

	/**
	 * Проходит по списку операций (equal/delete/insert) и группирует
	 * подряд идущие delete/insert в "разрывы" (gap) — то, что происходит
	 * между двумя одинаковыми предложениями. Внутри каждого разрыва
	 * пытаемся найти пары "удалено + добавлено", которые на самом деле —
	 * одно изменённое предложение.
	 *
	 * @param array<int, array{op:string, text:string}> $ops
	 *
	 * @return array<int, array{type:string, text:string, old?:string}>
	 */
	private function collapseModified(array $ops): array
	{
		$result = [];
		$buffer = []; // тут копим подряд идущие delete/insert одного "разрыва"

		// Вспомогательная функция: обработать накопленный буфер
		// и высыпать результат в $result
		$flushBuffer = function() use (&$buffer, &$result): void
		{
			if(empty($buffer))
			{
				return;
			}
			foreach($this->pairGap($buffer) as $item)
			{
				$result[] = $item;
			}
			$buffer = [];
		};

		foreach($ops as $op)
		{
			if($op['op'] === 'equal')
			{
				// Дошли до совпадающего предложения — значит, "разрыв"
				// (если он был) закончился, обрабатываем его целиком
				$flushBuffer();
				$result[] = ['type' => 'unchanged', 'text' => $op['text']];
			}
			else
			{
				// delete или insert — копим в буфер, пока не встретим equal
				$buffer[] = $op;
			}
		}
		// На случай, если текст заканчивается на delete/insert без equal в конце
		$flushBuffer();

		return $result;
	}

	/**
	 * Обрабатывает один "разрыв" — последовательность delete/insert
	 * между двумя одинаковыми предложениями. Задача: понять, какие
	 * из удалённых и добавленных предложений на самом деле — это
	 * одно и то же предложение, просто отредактированное.
	 *
	 * @param array<int, array{op:string, text:string}> $gap
	 *
	 * @return array<int, array{type:string, text:string, old?:string}>
	 */
	private function pairGap(array $gap): array
	{
		// Разделяем разрыв на две отдельные группы: что удалили, что добавили
		$deletes = [];
		$inserts = [];
		foreach($gap as $idx => $op)
		{
			if($op['op'] === 'delete')
			{
				$deletes[] = ['idx' => $idx, 'text' => $op['text']];
			}
			else
			{
				$inserts[] = ['idx' => $idx, 'text' => $op['text']];
			}
		}

		// Считаем "похожесть" каждого удалённого предложения
		// с каждым добавленным (все пары), оставляем только те пары,
		// где похожесть выше порога — это кандидаты на "изменено"
		$candidates = [];
		foreach($deletes as $di => $d)
		{
			foreach($inserts as $ii => $ins)
			{
				$sim = $this->similarity($d['text'], $ins['text']);
				if($sim >= self::SIMILARITY_THRESHOLD)
				{
					$candidates[] = ['sim' => $sim, 'd' => $di, 'i' => $ii];
				}
			}
		}

		// Сортируем кандидатов от самой похожей пары к самой непохожей
		usort($candidates, static fn($x, $y) => $y['sim'] <=> $x['sim']);

		// берём самую похожую пару, "бронируем" оба
		// предложения (чтобы они больше не участвовали), берём следующую
		// самую похожую из оставшихся и так далее
		$usedD = [];
		$usedI = [];
		$pairs = []; // индекс_удалённого => индекс_добавленного

		foreach($candidates as $c)
		{
			if(isset($usedD[$c['d']]) || isset($usedI[$c['i']]))
			{
				// Один из двух уже занят другой парой — пропускаем
				continue;
			}
			$usedD[$c['d']] = true;
			$usedI[$c['i']] = true;
			$pairs[$c['d']] = $c['i'];
		}

		$out = [];

		// Собираем найденные пары как "modified", сортируя по позиции
		// добавленного предложения в НОВОМ тексте (чтобы порядок
		// в результате совпадал с порядком чтения нового текста)
		$modifiedByInsertIdx = [];
		foreach($pairs as $di => $ii)
		{
			$modifiedByInsertIdx[$inserts[$ii]['idx']] = [
				'type' => 'modified',
				'text' => $inserts[$ii]['text'],   // новая версия — показываем по умолчанию
				'old'  => $deletes[$di]['text'],   // старая версия — показываем при hover
			];
		}
		ksort($modifiedByInsertIdx); // сортировка по ключу (позиции в тексте)
		foreach($modifiedByInsertIdx as $item)
		{
			$out[] = $item;
		}

		// Всё, что НЕ нашло себе пару среди удалённых — чистое удаление
		foreach($deletes as $di => $d)
		{
			if(!isset($usedD[$di]))
			{
				$out[] = ['type' => 'deleted', 'text' => $d['text']];
			}
		}

		// Всё, что не нашло себе пару среди добавленных — чистое добавление
		foreach($inserts as $ii => $ins)
		{
			if(!isset($usedI[$ii]))
			{
				$out[] = ['type' => 'added', 'text' => $ins['text']];
			}
		}

		return $out;
	}

	/**
	 * Считает "похожесть" двух предложений через коэффициент Жаккара:
	 * (количество общих слов) / (количество всех уникальных слов вместе).
	 *
	 * Пример: "кот сидел на окне" vs "кот лежал на окне"
	 * общие слова: кот, на, окне (3)
	 * все уникальные слова: кот, сидел, на, окне, лежал (5)
	 * похожесть = 3 / 5 = 0.6
	 */
	private function similarity(string $a, string $b): float
	{
		$wordsA = $this->words($a);
		$wordsB = $this->words($b);

		if(empty($wordsA) && empty($wordsB))
		{
			return 1.0; // оба пустые — считаем полностью похожими
		}
		if(empty($wordsA) || empty($wordsB))
		{
			return 0.0; // одно пустое, другое нет — совсем не похожи
		}

		// array_unique убирает повторы слов внутри одного предложения
		$setA = array_unique($wordsA);
		$setB = array_unique($wordsB);

		// Пересечение — слова, которые есть в обоих предложениях
		$intersection = count(array_intersect($setA, $setB));
		// Объединение — все уникальные слова из обоих предложений вместе
		$union = count(array_unique(array_merge($setA, $setB)));

		return $union > 0 ? $intersection / $union : 0.0;
	}

	/**
	 * Разбивает предложение на отдельные слова, приведённые к нижнему
	 * регистру, без знаков препинания. Используется для сравнения похожести.
	 *
	 * Пример: "Кот сидел, спал." -> ["кот", "сидел", "спал"]
	 *
	 * @return string[]
	 */
	private function words(string $sentence): array
	{
		$sentence = mb_strtolower($sentence, 'UTF-8');

		// Разбиваем по любому символу, который НЕ буква и НЕ цифра
		// (\p{L} = буква любого языка, \p{N} = цифра — с учётом юникода)
		$parts = preg_split('/[^\p{L}\p{N}]+/u', $sentence, -1, PREG_SPLIT_NO_EMPTY);

		return $parts === false ? [] : $parts;
	}
}