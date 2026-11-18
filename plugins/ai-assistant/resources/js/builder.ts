import type { BuilderApi } from '@/builder';
import { connectPageTools } from '@/pages/admin/plugins/ai-assistant/page-helper/tools';

// Adds the AI helper to the visual page editor: an "AI helper" top bar button and three section actions.
// They only appear while the page editor slot says the helper is ready (or, for admins, as a setup hint).
export default function register(api: BuilderApi): void {
    connectPageTools(api);
}
