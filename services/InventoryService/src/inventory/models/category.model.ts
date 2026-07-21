import { Field, ObjectType, Int } from '@nestjs/graphql';

@ObjectType()
export class Category {
  @Field(() => Int)
  id: number;

  @Field(() => Int, { nullable: true, defaultValue: 0 })
  parent_id?: number;

  @Field()
  name: string;

  @Field()
  slug: string;

  @Field({ nullable: true, defaultValue: '' })
  title?: string;

  @Field({ nullable: true, defaultValue: '' })
  description?: string;

  @Field({ nullable: true, defaultValue: '' })
  meta_title?: string;

  @Field({ nullable: true, defaultValue: '' })
  meta_description?: string;

  @Field({ nullable: true, defaultValue: '' })
  meta_keywords?: string;

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

  @Field({ nullable: true })
  deleted_at?: Date;
}

@ObjectType()
export class FieldError {
  @Field()
  field_name: string;

  @Field()
  message: string;
}

@ObjectType()
export class CategoryResponse {
  @Field()
  success: boolean;

  @Field()
  message: string;

  @Field(() => Category, { nullable: true })
  data?: Category;

  @Field(() => [FieldError], { nullable: true })
  error?: FieldError[];
}
