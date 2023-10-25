import React, { Suspense } from 'react';
import {
  ActionIcon,
  Button,
  Container,
  Divider,
  Flex,
  Grid,
  GridCol,
  Skeleton,
} from '@mantine/core';
import RubricForm from './RubricForm';
import Heading from '../heading/Heading';
import googleClassroom from '@/lib/config/googleClassroom';
import { IconChevronLeft } from '@tabler/icons-react';
import Link from 'next/link';
import Rubric from './RubricList';
import GenerateRubric from './GenerateRubric';

type Props = {
  params: {
    courseId: string;
    courseWorkId: string;
  };
};
const getCourseWork = async (courseId: string, id: string) => {
  const classroom = await googleClassroom();
  const { data: courseWork } = await classroom.courses.courseWork.get({
    courseId,
    id,
  });
  return courseWork;
};

const getCourse = async (courseId: string) => {
  //TODO: USE OPTIMISTIC FETCHING
  const classroom = await googleClassroom();
  const { data: course } = await classroom.courses.get({ id: courseId });
  return course;
};

export default async function RubricPage({
  params: { courseId, courseWorkId },
}: Props) {
  const courseWork = await getCourseWork(courseId, courseWorkId);
  const course = await getCourse(courseId);

  return (
    <Container mt='lg' size='md'>
      <Heading courseWork={courseWork} />
      <Flex mt='lg' justify={'space-between'} align='flex-end'>
        <Button
          variant='light'
          component={Link}
          href={'.'}
          leftSection={<IconChevronLeft />}
        >
          Back
        </Button>
        <GenerateRubric courseWork={courseWork} course={course} />
      </Flex>
      <Divider my='md' />
      <Grid>
        <GridCol span={{ base: 12, md: 6 }}>
          <RubricForm courseId={courseId} courseWorkId={courseWorkId} />
        </GridCol>
        <GridCol span={{ base: 12, md: 6 }}>
          <Suspense fallback={<Skeleton mt='lg' height={100} />}>
            <Rubric courseId={courseId} courseWorkId={courseWorkId} />
          </Suspense>
        </GridCol>
      </Grid>
    </Container>
  );
}
