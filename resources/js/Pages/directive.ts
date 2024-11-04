
export const vTooltip = {
    mounted(el, binding) {
        const modifiers = binding.modifiers;
        const tooltipText = typeof binding.value === 'string' ? binding.value : binding.value?.text || '';
        let position = 'top';

        if (modifiers.top) position = 'top';
        else if (modifiers['top-left']) position = 'top-left';
        else if (modifiers['top-right']) position = 'top-right';
        else if (modifiers['top-center']) position = 'top-center';
        else if (modifiers.bottom) position = 'bottom';
        else if (modifiers['bottom-left']) position = 'bottom-left';
        else if (modifiers['bottom-right']) position = 'bottom-right';
        else if (modifiers['bottom-center']) position = 'bottom-center';
        else if (modifiers.left) position = 'left';
        else if (modifiers.right) position = 'right';
        else if (modifiers['right-bottom']) position = 'right-bottom';
        else if (modifiers['left-bottom']) position = 'left-bottom';
        else if (modifiers['right-top']) position = 'right-top';
        else if (modifiers['left-top']) position = 'left-top';

        // Create tooltip element
        const tooltipEl = document.createElement('span');
        tooltipEl.className = 'v-tooltip';
        tooltipEl.textContent = tooltipText;
        tooltipEl.style.position = 'absolute';
        tooltipEl.style.whiteSpace = 'nowrap';
        tooltipEl.style.padding = '5px 10px';
        tooltipEl.style.backgroundColor = 'rgb(var(--box-bg-color, 0, 0, 0))';
        tooltipEl.style.color = 'rgb(var(--box-font-color, 255, 255, 255))';
        tooltipEl.style.border = '1px solid rgb(var(--box-border-color, 255, 255, 255))';
        tooltipEl.style.borderRadius = '4px';
        tooltipEl.style.display = 'none';
        tooltipEl.style.zIndex = '1000';

        // Create arrow element
        const arrowEl = document.createElement('span');
        arrowEl.className = 'v-tooltip-arrow';
        arrowEl.style.position = 'absolute';
        arrowEl.style.width = '0';
        arrowEl.style.height = '0';
        arrowEl.style.borderStyle = 'solid';
        tooltipEl.appendChild(arrowEl);



        // Show tooltip
        el.addEventListener('mouseenter', () => {
            // Create tooltip element
            const tooltipEl = document.createElement('span');
            tooltipEl.className = 'v-tooltip';
            tooltipEl.textContent = tooltipText;
            tooltipEl.style.position = 'absolute';
            tooltipEl.style.whiteSpace = 'nowrap';
            tooltipEl.style.padding = '5px 10px';
            tooltipEl.style.backgroundColor = 'rgb(var(--box-bg-color, 0, 0, 0))';
            tooltipEl.style.color = 'rgb(var(--box-font-color, 255, 255, 255))';
            tooltipEl.style.border = '1px solid rgb(var(--box-border-color, 255, 255, 255))';
            tooltipEl.style.borderRadius = '4px';
            tooltipEl.style.zIndex = '1000';

            // Create arrow element
            const arrowEl = document.createElement('span');
            arrowEl.className = 'v-tooltip-arrow';
            arrowEl.style.position = 'absolute';
            arrowEl.style.width = '0';
            arrowEl.style.height = '0';
            arrowEl.style.borderStyle = 'solid';
            tooltipEl.appendChild(arrowEl);

            el.appendChild(tooltipEl);
            el._tooltipEl = tooltipEl;
            el._arrowEl = arrowEl;

            const rect = el.getBoundingClientRect();
            tooltipEl.style.display = 'block';

            switch (position) {
                case 'top':
                    tooltipEl.style.left = `${rect.width / 2 - tooltipEl.offsetWidth / 2}px`;
                    tooltipEl.style.top = `-${tooltipEl.offsetHeight + 8}px`;
                    arrowEl.style.left = '50%';
                    arrowEl.style.bottom = '-7px';
                    arrowEl.style.transform = 'translateX(-50%)';
                    arrowEl.style.borderWidth = '7px 7px 0 7px';
                    arrowEl.style.borderColor = 'rgb(var(--box-bg-color, 255, 255, 255)) transparent transparent transparent';
                    break;
                case 'top-left':
                    tooltipEl.style.left = `0px`;
                    tooltipEl.style.top = `-${tooltipEl.offsetHeight + 8}px`;
                    arrowEl.style.left = '10px';
                    arrowEl.style.bottom = '-7px';
                    arrowEl.style.borderWidth = '7px 7px 0 7px';
                    arrowEl.style.borderColor = 'rgb(var(--box-bg-color, 255, 255, 255)) transparent transparent transparent';
                    break;
                case 'top-right':
                    tooltipEl.style.left = `${rect.width - tooltipEl.offsetWidth}px`;
                    tooltipEl.style.top = `-${tooltipEl.offsetHeight + 8}px`;
                    arrowEl.style.right = '10px';
                    arrowEl.style.bottom = '-7px';
                    arrowEl.style.borderWidth = '7px 7px 0 7px';
                    arrowEl.style.borderColor = 'rgb(var(--box-bg-color, 255, 255, 255)) transparent transparent transparent';
                    break;
                case 'bottom':
                    tooltipEl.style.left = `${rect.width / 2 - tooltipEl.offsetWidth / 2}px`;
                    tooltipEl.style.top = `${rect.height + 8}px`;
                    arrowEl.style.left = '50%';
                    arrowEl.style.top = '-5px';
                    arrowEl.style.transform = 'translateX(-50%)';
                    arrowEl.style.borderWidth = '0 5px 5px 5px';
                    arrowEl.style.borderColor = 'transparent transparent rgb(var(--box-bg-color, 255, 255, 255)) transparent';
                    break;
                case 'bottom-left':
                    tooltipEl.style.left = `0px`;
                    tooltipEl.style.top = `${rect.height + 8}px`;
                    arrowEl.style.left = '10px';
                    arrowEl.style.top = '-5px';
                    arrowEl.style.borderWidth = '0 5px 5px 5px';
                    arrowEl.style.borderColor = 'transparent transparent rgb(var(--box-bg-color, 255, 255, 255)) transparent';
                    break;
                case 'bottom-right':
                    tooltipEl.style.left = `${rect.width - tooltipEl.offsetWidth}px`;
                    tooltipEl.style.top = `${rect.height + 8}px`;
                    arrowEl.style.right = '10px';
                    arrowEl.style.top = '-5px';
                    arrowEl.style.borderWidth = '0 5px 5px 5px';
                    arrowEl.style.borderColor = 'transparent transparent rgb(var(--box-bg-color, 255, 255, 255)) transparent';
                    break;
                case 'left':
                    tooltipEl.style.left = `-${tooltipEl.offsetWidth + 8}px`;
                    tooltipEl.style.top = `${rect.height / 2 - tooltipEl.offsetHeight / 2}px`;
                    arrowEl.style.right = '-5px';
                    arrowEl.style.top = '50%';
                    arrowEl.style.transform = 'translateY(-50%)';
                    arrowEl.style.borderWidth = '7px 7px 0 7px';
                    arrowEl.style.borderColor = 'transparent transparent transparent rgb(var(--box-bg-color, 255, 255, 255))';
                    break;
                case 'right':
                    tooltipEl.style.left = `${rect.width + 8}px`;
                    tooltipEl.style.top = `${rect.height / 2 - tooltipEl.offsetHeight / 2}px`;
                    arrowEl.style.left = '-5px';
                    arrowEl.style.top = '50%';
                    arrowEl.style.transform = 'translateY(-50%)';
                    arrowEl.style.borderWidth = '7px 7px 0 7px';
                    arrowEl.style.borderColor = 'transparent rgb(var(--box-bg-color, 255, 255, 255)) transparent transparent';
                    break;
                case 'right-bottom':
                    tooltipEl.style.left = `${rect.width + 8}px`;
                    tooltipEl.style.top = `${rect.height - tooltipEl.offsetHeight}px`;
                    arrowEl.style.left = '-5px';
                    arrowEl.style.bottom = '10px';
                    arrowEl.style.borderWidth = '7px 7px 0 7px';
                    arrowEl.style.borderColor = 'transparent rgb(var(--box-bg-color, 255, 255, 255)) transparent transparent';
                    break;
                case 'left-bottom':
                    tooltipEl.style.left = `-${tooltipEl.offsetWidth + 8}px`;
                    tooltipEl.style.top = `${rect.height - tooltipEl.offsetHeight}px`;
                    arrowEl.style.right = '-5px';
                    arrowEl.style.bottom = '10px';
                    arrowEl.style.borderWidth = '7px 7px 0 7px';
                    arrowEl.style.borderColor = 'transparent transparent transparent rgb(var(--box-bg-color, 255, 255, 255))';
                    break;
                case 'right-top':
                    tooltipEl.style.left = `${rect.width + 8}px`;
                    tooltipEl.style.top = `0px`;
                    arrowEl.style.left = '-5px';
                    arrowEl.style.top = '10px';
                    arrowEl.style.borderWidth = '7px 7px 0 7px';
                    arrowEl.style.borderColor = 'transparent rgb(var(--box-bg-color, 255, 255, 255)) transparent transparent';
                    break;
                case 'left-top':
                    tooltipEl.style.left = `-${tooltipEl.offsetWidth + 8}px`;
                    tooltipEl.style.top = `0px`;
                    arrowEl.style.right = '-5px';
                    arrowEl.style.top = '10px';
                    arrowEl.style.borderWidth = '7px 7px 0 7px';
                    arrowEl.style.borderColor = 'transparent transparent transparent rgb(var(--box-bg-color, 255, 255, 255))';
                    break;
                default:
                    console.warn(`Unsupported tooltip position: ${position}`);
            }
        });

        // Hide tooltip
        el.addEventListener('mouseleave', () => {
            if (el._tooltipEl) {
                el.removeChild(el._tooltipEl);
                delete el._tooltipEl;
                delete el._arrowEl;
            }
        });
    },
    unmounted(el) {
        if (el._tooltipEl) {
            el.removeChild(el._tooltipEl);
            delete el._tooltipEl;
        }
    },
};