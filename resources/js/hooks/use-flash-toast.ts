import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

// Event the admin shell listens to, to open a sidebar item by its key.
export const OPEN_NAV_EVENT = 'verso:open-nav';

// Shows the toast that the server flashed. A toast can have a button that opens a sidebar item.
export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            const action = data.action;

            toast[data.type](
                data.message,
                action
                    ? {
                          action: {
                              label: action.label,
                              onClick: () =>
                                  window.dispatchEvent(
                                      new CustomEvent(OPEN_NAV_EVENT, {
                                          detail: action.navKey,
                                      }),
                                  ),
                          },
                      }
                    : undefined,
            );
        });
    }, []);
}
