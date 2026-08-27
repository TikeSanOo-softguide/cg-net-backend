import {
    Children,
    Fragment,
    cloneElement,
    isValidElement,
    useEffect,
    useId,
    useLayoutEffect,
    useRef,
    useState,
    type CSSProperties,
    type MouseEvent,
    type ReactElement,
    type ReactNode,
} from 'react';
import { createPortal } from 'react-dom';
import { MoreVerticalIcon } from 'lucide-react';

import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

type RadialBubbleActionsProps = {
    children: ReactNode;
    /** `around` fans over the trigger. `end` fans to the right, for a control beside an image. */
    placement?: 'around' | 'end';
};

const OPEN_EVENT = 'datatable-radial-open';
const RADIUS = 36;
const BUBBLE = 28;

function flattenActions(children: ReactNode): ReactElement[] {
    return Children.toArray(children).flatMap((child) => {
        if (! isValidElement(child)) {
            return [];
        }

        if (child.type === Fragment) {
            return flattenActions((child.props as { children?: ReactNode }).children);
        }

        return [child];
    });
}

const CARDINAL_ANGLES: Record<number, number[]> = {
    1: [0],
    2: [-90, 90],
    3: [-90, 0, 90],
    4: [-90, 0, 90, 180],
};

function bubbleAngle(index: number, count: number): number {
    const layout = CARDINAL_ANGLES[count];

    if (layout) {
        return layout[index] ?? 0;
    }

    return (index * 360) / count;
}

function bubblePosition(index: number, count: number): { x: number; y: number; angle: number } {
    const angle = bubbleAngle(index, count);
    const radians = (angle * Math.PI) / 180;

    return {
        angle,
        x: Math.sin(radians) * RADIUS,
        y: -Math.cos(radians) * RADIUS,
    };
}

export function RadialBubbleActions({ children }: RadialBubbleActionsProps) {
    const { t } = useTranslation();
    const instanceId = useId();
    const triggerRef = useRef<HTMLButtonElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);
    const items = flattenActions(children);
    const [open, setOpen] = useState(false);
    const [origin, setOrigin] = useState({ x: 0, y: 0 });

    useLayoutEffect(() => {
        if (! open || ! triggerRef.current) {
            return;
        }

        const rect = triggerRef.current.getBoundingClientRect();
        setOrigin({
            x: rect.left + rect.width / 2,
            y: rect.top + rect.height / 2,
        });
    }, [open]);

    useEffect(() => {
        if (! open) {
            return;
        }

        const close = () => setOpen(false);
        const onOutsideClick = (event: Event) => {
            const target = event.target as Node;

            if (triggerRef.current?.contains(target) || menuRef.current?.contains(target)) {
                return;
            }

            close();
        };
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                close();
            }
        };
        const onOtherOpen = (event: Event) => {
            if ((event as CustomEvent<string>).detail !== instanceId) {
                close();
            }
        };

        window.addEventListener(OPEN_EVENT, onOtherOpen);
        window.addEventListener('click', onOutsideClick);
        window.addEventListener('keydown', onKeyDown);
        window.addEventListener('scroll', close, true);

        return () => {
            window.removeEventListener(OPEN_EVENT, onOtherOpen);
            window.removeEventListener('click', onOutsideClick);
            window.removeEventListener('keydown', onKeyDown);
            window.removeEventListener('scroll', close, true);
        };
    }, [instanceId, open]);

    if (items.length === 0) {
        return null;
    }

    const toggle = (event: MouseEvent<HTMLButtonElement>) => {
        event.stopPropagation();
        event.preventDefault();

        setOpen((current) => {
            const next = ! current;

            if (next) {
                window.dispatchEvent(new CustomEvent(OPEN_EVENT, { detail: instanceId }));
            }

            return next;
        });
    };

    return (
        <div className="relative isolate flex size-7 items-center justify-center">
            <button
                ref={triggerRef}
                type="button"
                aria-label={t('common.actions')}
                aria-expanded={open}
                aria-haspopup="menu"
                className={cn(
                    'relative z-[2] inline-flex size-7 items-center justify-center rounded-[6px]',
                    'bg-primary/12 text-primary leading-none transition-all duration-200 ease-out',
                    'hover:scale-105 hover:bg-primary hover:text-primary-foreground hover:shadow-sm active:scale-95',
                    'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 focus-visible:outline-none',
                    open && 'bg-primary text-primary-foreground shadow-sm',
                )}
                onClick={toggle}
            >
                <MoreVerticalIcon
                    className={cn('size-3.5 transition-transform duration-200', open && 'rotate-90')}
                    strokeWidth={1.85}
                />
            </button>
            {open && typeof document !== 'undefined'
                ? createPortal(
                    <div
                        ref={menuRef}
                        role="menu"
                        className="pointer-events-none fixed z-[70]"
                        style={{ left: origin.x, top: origin.y }}
                    >
                        {items.map((item, index) => {
                            const { x, y, angle } = bubblePosition(index, items.length);
                            const delay = `${index * 120}ms`;
                            const timing = `1000ms cubic-bezier(0.16, 1, 0.3, 1) ${delay} both`;

                            return (
                                <div
                                    key={item.key ?? index}
                                    role="none"
                                    className="pointer-events-auto absolute flex items-center justify-center"
                                    style={{
                                        width: BUBBLE,
                                        height: BUBBLE,
                                        left: x,
                                        top: y,
                                        marginLeft: -BUBBLE / 2,
                                        marginTop: -BUBBLE / 2,
                                        transformOrigin: `calc(50% - ${x}px) calc(50% - ${y}px)`,
                                        '--from-angle': '0deg',
                                        '--to-angle': `${angle}deg`,
                                        animation: `radial-bubble-in ${timing}`,
                                    } as CSSProperties}
                                    onClick={() => setOpen(false)}
                                >
                                    <div
                                        className="flex size-full items-center justify-center"
                                        style={{ animation: `radial-icon-level ${timing}` } as CSSProperties}
                                    >
                                        {cloneElement(item, { size: 'sm', inverted: true } as { size: 'sm'; inverted: true })}
                                    </div>
                                </div>
                            );
                        })}
                    </div>,
                    document.body,
                )
                : null}
        </div>
    );
}
