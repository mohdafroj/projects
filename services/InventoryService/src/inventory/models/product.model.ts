import { Field, Float, Int, ObjectType } from '@nestjs/graphql';

@ObjectType()
export class Product {
  @Field(() => Int)
  id: number;

  @Field(() => Int)
  category_id: number;

  @Field()
  name: string;

  @Field()
  slug: string;

  @Field({ nullable: true, defaultValue: '' })
  description?: string;

  @Field(() => Float)
  price: number;

  @Field(() => Int, { nullable: true, defaultValue: 0 })
  quantity?: number;

  @Field({ nullable: true, defaultValue: '' })
  image?: string;

  @Field({ nullable: true, defaultValue: true })
  status?: boolean;

  @Field({ nullable: true, defaultValue: false })
  soft_deleted?: boolean;

  @Field(() => Int, { nullable: true, defaultValue: 0 })
  sort?: number;

  @Field(() => Int, { nullable: true })
  created_by?: number;

  @Field(() => Int, { nullable: true })
  updated_by?: number;

  @Field(() => Int, { nullable: true })
  deleted_by?: number;

  @Field({ nullable: true })
  created_at?: Date;

  @Field({ nullable: true })
  updated_at?: Date;
}
