const oldTextEl = document.getElementById('old-text');
const newTextEl = document.getElementById('new-text');
const compareBtn = document.getElementById('compare-btn');
const statusEl = document.getElementById('status');
const resultEl = document.getElementById('result');

compareBtn.addEventListener('click', handleCompare);

async function handleCompare()
{
    const formData = new FormData();
    formData.append('old_text', oldTextEl.value);
    formData.append('new_text', newTextEl.value);

    compareBtn.disabled = true;
    statusEl.textContent = 'Сравниваем...';

    try
    {
        const response = await fetch('api/compare', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (!response.ok)
        {
            throw new Error(data.error || 'Ошибка запроса');
        }

        renderResult(data.result);
        statusEl.textContent = '';
    } catch (err)
    {
        statusEl.textContent = 'Ошибка: ' + err.message;
    } finally
    {
        compareBtn.disabled = false;
    }
}

function renderResult(segments)
{
    resultEl.innerHTML = '';

    if (!segments || segments.length === 0)
    {
        resultEl.textContent = 'Тексты идентичны либо пусты.';
        return;
    }

    const fragment = document.createDocumentFragment();

    segments.forEach((segment) => {
        const span = document.createElement('span');
        span.classList.add('sentence--' + segment.type);
        span.textContent = segment.text;

        if (segment.type === 'modified' && segment.old)
        {
            span.dataset.newText = segment.text;
            span.dataset.oldText = segment.old;

            span.addEventListener('mouseenter', () => {
                span.textContent = span.dataset.oldText;
            });
            span.addEventListener('mouseleave', () => {
                span.textContent = span.dataset.newText;
            });
        }

        fragment.appendChild(span);
        fragment.appendChild(document.createTextNode(' '));
    });

    resultEl.appendChild(fragment);
}