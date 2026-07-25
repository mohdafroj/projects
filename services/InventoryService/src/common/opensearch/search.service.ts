import { Injectable } from '@nestjs/common';
import { Client } from '@opensearch-project/opensearch';

@Injectable()
export class SearchService {
    private readonly client = new Client({
        node: process.env.OPENSEARCH_URL,
    });

    async createProduct(product: any) {
        return this.client.index({
            index: 'products',
            body: product,
            refresh: true,
        });
    }

    async search(keyword: string) {
        return this.client.search({
            index: 'products',
            body: {
                query: {
                    match: {
                        name: keyword,
                    },
                },
            },
        });
    }
}