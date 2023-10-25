'use server';
import prisma from '@/lib/db';
import { Criterion } from '@prisma/client';
import { revalidatePath } from 'next/cache';
import { z } from 'zod';

const schema = z.object({
  title: z.string(),
  description: z.string().optional(),
  points: z.coerce.number().positive(),
});

export async function createRubric(
  courseId: string,
  courseWorkId: string,
  items: object[]
) {
  const criteria = z.array(schema).parse(items);

  await prisma.criterion.deleteMany({
    where: {
      courseId: courseId,
      courseWorkId: courseWorkId,
    },
  });

  await prisma.rubric.deleteMany({
    where: {
      courseId: courseId,
      courseWorkId: courseWorkId,
    },
  });

  await prisma.rubric.create({
    data: {
      courseId: courseId,
      courseWorkId: courseWorkId,
      criteria: {
        create: criteria,
      },
    },
  });

  revalidatePath(`/courses/${courseId}/work/${courseWorkId}/rubric`);
}

export async function addCriteria(
  courseId: string,
  courseWorkId: string,
  obj: object
) {
  const data = schema.parse(obj);

  await prisma.criterion.create({
    data: {
      rubric: {
        connectOrCreate: {
          where: {
            courseId_courseWorkId: {
              courseId: courseId,
              courseWorkId: courseWorkId,
            },
          },
          create: {
            courseId: courseId,
            courseWorkId: courseWorkId,
          },
        },
      },
      ...data,
    },
  });

  revalidatePath(`/courses/${courseId}/work/${courseWorkId}/rubric`);
}

export const deleteCriterion = async (item: Criterion) => {
  await prisma.criterion.delete({
    where: {
      id: item.id,
    },
  });

  revalidatePath(`/courses/${item.courseId}/work/${item.courseWorkId}/rubric`);
};
